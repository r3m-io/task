<?php
namespace Package\R3m\Io\Task\Trait;

use R3m\Io\Config;

use R3m\Io\Module\Core;
use R3m\Io\Module\File;
use R3m\Io\Module\Dir;

use R3m\Io\Node\Model\Node;

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
                        'attribute' => 'status',
                        'operator' => '==='
                    ]
                ],
                'sort' => [
                    'options.priority' => 'ASC',
                    'is.created' => 'ASC'
                ]
            ]
        );
        return $result;
    }
}