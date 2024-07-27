<?php
namespace Package\R3m\Io\Task\Trait;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Core;
use R3m\Io\Module\Data;
use R3m\Io\Module\Destination;
use R3m\Io\Module\Event;
use R3m\Io\Module\File;
use R3m\Io\Module\Dir;

use R3m\Io\Module\Handler;
use R3m\Io\Module\OutputFilter;
use R3m\Io\Module\Response;
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

        if(!property_exists($options, 'thread')){
            $options->thread = 8;
        }

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
            $count = 0;
            foreach($result['list'] as $nr => $task){
                $task = $this->not_before($task);
                $queue = $this->queue($queue, $task, $count);
            }
            if($count > 0){
                $chunks = array_chunk($queue, ceil($count / $options->thread));
                $this->parallel($chunks, $options);
            }
        }
        echo 'Done...' . PHP_EOL;
//        return $result;
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     */
    private function parallel($chunks=[], $options){
        $object = $this->object();
        if (
            property_exists($options, 'ramdisk_dir') &&
            $options->ramdisk_dir !== false
        ) {
            $ramdisk_dir = $options['ramdisk_dir'];
        } else {
            $ramdisk_dir = $object->config('ramdisk.url') .
                $object->config('posix.id') .
                $object->config('ds');
        }
        $ramdisk_dir_node = $ramdisk_dir .
            'Node' .
            $object->config('ds')
        ;
        $ramdisk_dir_parallel = $ramdisk_dir_node .
            'Parallel' .
            $object->config('ds')
        ;
        if(!Dir::exist($ramdisk_dir_parallel)){
            Dir::create($ramdisk_dir_parallel, Dir::CHMOD);
            File::permission($object, [
                'ramdisk_dir' => $ramdisk_dir,
                'ramdisk_dir_node' => $ramdisk_dir_node,
                'ramdisk_dir_parallel' => $ramdisk_dir_parallel,
            ]);
        }
        $name = Task::NODE;
        for ($i = 0; $i < $options->thread; $i++) {
            // Create a pipe
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                die("Unable to create socket pair for child $i");
            }
            $key_options = [
                'time' => time()
            ];
            $key = sha1(Core::object($key_options, Core::OBJECT_JSON));
            $url[$i] = $ramdisk_dir_parallel .
                $name .
                '.' .
                $key .
                '.' .
                $i .
                $object->config('extension.json');
            if(array_key_exists($i, $chunks)){
                $chunk = $chunks[$i];
                $pid = pcntl_fork();
                if ($pid == -1) {
                    die("Could not fork for child $i");
                } elseif ($pid) {
                    // Parent process
                    // Close the child's socket
                    fclose($sockets[0]);
                    // Store the parent socket and child PID
                    $pipes[$i] = $sockets[1];
                    $children[$i] = $pid;
                } else {
                    // Child process
                    // Close the parent's socket
                    fclose($sockets[1]);
                    $result = [];
                    foreach($chunk as $nr => $task) {
                        $result[] = $this->run_task($task);
                    }
                    // Send serialized data to the parent
                    File::write($url[$i], Core::object($result, Core::OBJECT_JSON_LINE));
//                    fwrite($sockets[0], 1);
                    fclose($sockets[0]);
                    exit(0);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function run_task($task){
        $object = $this->object();
        $data = new Data($task);
        $node = new Node($object);
        $patch = new Data();

        $patch->set('uuid', $data->get('uuid'));
        $patch->set('options.status', Task::OPTIONS_STATUS_RUNNING);
//        $node->patch(Task::NODE, $node->role_system(), $patch->data());
        try {
            $response = [];
            $command = $data->get('options.command');
            $count = 0;
            if(is_array($command)){
                foreach($command as $execute){
                    Core::execute($object, $execute, $output, $notification);
                    $response[] = (object) [
                        'command' => $execute,
                        'output' => $output,
                        'notification' => $notification
                    ];
                    $count++;
                }
            }
            $controller = $data->get('options.controller');
            if(is_array($controller)){
                foreach($controller as $execute){
                    App::contentType($object);
                    $destination = new Destination();
                    $destination->set('controller', $execute);
                    App::controller($object, $destination);
                    $controller = $destination->get('controller');
                    $methods = get_class_methods($controller);
                    if (empty($methods)) {
                        $exception = new Exception(
                            'Couldn\'t determine controller (' . $destination->get('controller') . ')'
                        );
                        $response[] = new Response(
                            App::exception_to_json($exception),
                            Response::TYPE_JSON,
                            Response::STATUS_NOT_IMPLEMENTED
                        );
                    }
                    $function = $destination->get('function');
                    $methods = get_class_methods($controller);
                    if(
                        $destination &&
                        $function &&
                        $methods &&
                        in_array($function, $methods, true)
                    ) {
                        $functions[] = $function;
                        $object->config('controller.function', $function);
                        $request = Core::deep_clone(
                            $object->get(
                                App::NAMESPACE . '.' .
                                Handler::NAME_REQUEST . '.' .
                                Handler::NAME_INPUT
                            )->data()
                        );
                        $object->config('request', $request);
                        $result = $controller::{$function}($object);
                        Event::trigger($object, 'app.run.route.controller', [
                            'destination' => $destination,
                            'response' => $result
                        ]);
                        $result = OutputFilter::trigger($object, $destination, [
                            'methods' => $methods,
                            'response' => $result
                        ]);
                        $response[] = $result;
                        $count++;
                    }
                }
            }
            $patch->set('options.status', Task::OPTIONS_STATUS_DONE);
            if($count === 1){
                $patch->set('options.response', $response[0] ?? (object) []);
            } else {
                $patch->set('options.response', $response);
            }
//            $node->patch(Task::NODE, $node->role_system(), $patch->data());
        }
        catch(Exception $exception){
            $patch->set('options.status', Task::OPTIONS_STATUS_EXCEPTION);
            $patch->set('options.exception', explode(PHP_EOL, (string) $exception));
            $node->patch(Task::NODE, $node->role_system(), $patch->data());
        }
        return $patch->get('options.response');
    }

    /**
     * @throws Exception
     */
    private function not_before($task){
        $object = $this->object();
        $data = new Data($task);
        $patch = new Data();
        $patch->set('uuid', $data->get('uuid'));
        $time = time();
        if($data->has('options.not_before')){
            $not_before = $data->get('options.not_before');
            $is_set = false;
            if($time < $not_before){
                if($data->get('options.status') !== Task::OPTIONS_STATUS_WAITING){
                    // update status to waiting
                    // a waiting task gets updated to status 'queue' every minute until not_before is reached
                    $patch->set('options.status', Task::OPTIONS_STATUS_WAITING);
                    $is_set = true;
                }
            } else {
                if($data->get('options.status') === Task::OPTIONS_STATUS_WAITING){
                    $patch->set('options.status', Task::OPTIONS_STATUS_QUEUE);
                    $is_set = true;
                }
            }
            if($is_set){
                $node = new Node($object);
                $node->patch(Task::NODE, $node->role_system(), $patch->data());
                $data->set('options.status', $patch->get('options.status'));
            }
        }
        return $data->data();
    }

    /**
     * @throws Exception
     */
    private function queue($queue=[], $task, &$count=0){
        $data = new Data($task);
        if($data->get('options.status') === Task::OPTIONS_STATUS_QUEUE){
            $queue[] = $data->data();
            $count++;
        }
        return $queue;
    }
}