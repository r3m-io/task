<?php
namespace Package\R3m\Io\Task\Trait;

use R3m\Io\Config;

use R3m\Io\Module\Cli;

use R3m\Io\Node\Model\Node;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

trait Main {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function create($flags, $options){
        d($flags);
        d($options);
        $object = $this->object();
        $node = new Node($object);



        if(property_exists($options, 'description')){
            if(is_array($options->description)){
                $options->description = implode(PHP_EOL, $options->description);
            } else {
                $options->description = $options->description;
            }
        }
        if(
            property_exists($options, 'options') &&
            property_exists($options->options, 'command')
        ){
            if(is_array($options->options->command)){
                //nothing
            } elseif(is_scalar($options->options->command)){
                $options->options->command = [ $options->options->command ];
            }
        }
        if(
            property_exists($options, 'options') &&
            property_exists($options->options, 'controller')
        ){
            if(is_array($options->options->controller)){
                //nothing
            } elseif(is_scalar($options->options->controller)){
                $options->options->controller = [ $options->options->controller ];
            }
        }
        if(property_exists($options, 'options')){
            $options->options->request = $object->request();
            $options->options->server = $object->server();
            $options->options->flags = $flags;
            $options->options->status  = 'queue';
            $options->options->priority = 100;
        } else {
            throw new Exception('options not found (controller / command)');
        }
        $username = Cli::read('input', 'username: ');
        $password = Cli::read('input-hidden', 'password: ');

        //url of backend to login

        //first get all hosts

        $hosts = $node->list(
            'System.Host',
            $node->role_system(),
            [

            ]
        );

        if(
            array_key_exists('count', $hosts) &&
            array_key_exists('list', $hosts) &&
            $hosts['count'] > 0
        ){
            echo 'Select host to connect to: ' . PHP_EOL;
            $url = [];
            foreach($hosts['list'] as $nr => $host){
                $index = $nr + 1;
                $url[$index] = $host;
                $url_environment = $host->url->{$object->config('framework.environment')};
                echo '[' . $index . '] ' . $url_environment  . PHP_EOL;
            }
            while(true){
                $index_read = (int) Cli::read('input', 'Host number: ');
                if(
                    $index_read > 0 &&
                    $index_read <= $hosts['count']
                ){
                    break;
                }
            }
//            $url_login = $url[$index_read] . '/login';

            $route = $node->list(
                'System.Route',
                $node->role_system(),
                [
                    'filter' => [
                        'host' => strtolower($url[$index_read]->name)
                    ]
                ]
            );
            ddd($route);

        }
        ddd($hosts);

        $options->options->server->authorization = 'Bearer ' . $token;


        d($username);
        d($password);

        d($options);

        $create = $node->create(
            'Task',
            $node->role_system(),
            $options
        );
        ddd($create);
        return $create;
    }
}