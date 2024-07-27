<?php
namespace Package\R3m\Io\Task\Trait;

use R3m\Io\Config;

use R3m\Io\Module\Core;
use R3m\Io\Module\Data;
use R3m\Io\Module\File;
use R3m\Io\Module\Dir;

use R3m\Io\Node\Model\Node;

use Package\R3m\Io\Task\Service\Task;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

trait Service {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function execute($flags, $options){
        d($flags);
        d($options);
        $object = $this->object();
        $node = new Node($object);
        $result = $node->list(
            'Task',
            $node->role_system(),
            [
                'where' => [
                    [
                        'value' => 'queue',
                        'attribute' => 'options.status',
                        'operator' => '==='
                    ]
                ],
                'sort' => [
                    'options.priority' => 'ASC',
                    'is.created' => 'ASC'
                ]
            ]
        );
        if(
            array_key_exists('count', $result) &&
            array_key_exists('list', $result) &&
            $result['count'] >= 0
        ){
            $queue = [];
            foreach($result['list'] as $nr => $task){
                $task = $this->not_before($task);
                $queue = $this->queue($queue, $task);
            }
            d($queue);
            d(count($queue));
        }
        echo 'Done...' . PHP_EOL;
//        return $result;
    }

    /**
     * @throws Exception
     */
    private function not_before($task){
        $object = $this->object();
        $data = new Data($task);
        $time = time();
        if($data->has('options.not_before')){
            $not_before = $data->get('options.not_before');
            $is_set = false;
            if($time < $not_before){
                if($data->get('options.status') !== Task::OPTIONS_STATUS_WAITING){
                    // update status to waiting
                    // a waiting task gets updated to status 'queue' every minute until not_before is reached
                    $data->set('options.status', Task::OPTIONS_STATUS_WAITING);
                    $is_set = true;
                }
            } else {
                if($data->get('options.status') === Task::OPTIONS_STATUS_WAITING){
                    $data->set('options.status', Task::OPTIONS_STATUS_QUEUE);
                    $is_set = true;
                }
            }
            if($is_set){
                $node = new Node($object);
                $node->patch(Task::NODE, $node->role_system(), $data->data());
            }
        }
        return $data->data();
    }

    /**
     * @throws Exception
     */
    private function queue($queue=[], $task){
        $data = new Data($task);
        if($data->get('options.status') === Task::OPTIONS_STATUS_QUEUE){
            $queue[] = $data->data();
        }
        return $queue;
    }
}