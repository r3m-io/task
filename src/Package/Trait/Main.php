<?php
namespace Package\R3m\Io\Task\Trait;

use GuzzleHttp\Exception\GuzzleException;
use R3m\Io\Config;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Core;

use R3m\Io\Node\Model\Node;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;

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
//        d($flags);
//        d($options);
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


        //url of backend to login

        //first get all hosts

        $hosts = $node->list(
            'System.Host',
            $node->role_system(),
            [

            ]
        );
        $index = false;
        $index_read = false;
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
                $index_read = Cli::read('input', 'Host number: ');
                if(strtolower($index_read) === 'add'){
                    ddd('add');
                    break;
                } else {
                    $index_read = (int) $index_read;
                }
                if(
                    $index_read > 0 &&
                    $index_read <= $hosts['count']
                ){
                    break;
                }
            }
//            $url_login = $url[$index_read] . '/login';

            $route = $node->record(
                'System.Route',
                $node->role_system(),
                [
                    'filter' => [
                        'host' => strtolower($url[$index_read]->name),
                        'name' => 'user-login'
                    ]
                ]
            );
            if(!$route && $index_read){
                echo 'No route "user-login" found !' . PHP_EOL;
                $add = Cli::read('input', 'Would you like to add user-login (y/n): ');
                if(strtolower(substr($add, 0, 1)) === 'y'){
                    $controller =
                        'Domain' .
                        ':' .
                        $url[$index_read]->name .
                        ':' .
                        'Controller' .
                        ':' .
                        'User'
                    ;
                    $route = $node->create_many('System.route', $node->role_system(), [
                        (object) [
                            "name" => "user-login",
                            "host" => strtolower($url[$index_read]->name),
                            "controller" =>  $controller . ':' . 'user.login',
                            "path" =>  "/User/Login/",
                            "priority" => 2003,
                            "method" => [
                                "POST"
                            ],
                            "request" => ( object ) [
                                "language" => "en"
                            ]
                        ],
                        (object) [
                            "name" => "user-current",
                            "host" => strtolower($url[$index_read]->name),
                            "controller" =>  $controller . ':' . 'user.current',
                            "path" =>  "/User/Current/",
                            "priority" => 2003,
                            "method" => [
                                "POST"
                            ],
                            "request" => ( object ) [
                                "language" => "en"
                            ]
                        ],
                        (object) [
                            "name" => "user-refresh-token",
                            "host" => strtolower($url[$index_read]->name),
                            "controller" =>  $controller . ':' . 'user.refresh.token',
                            "path" =>  "/User/Refresh/Token/",
                            "priority" => 2003,
                            "method" => [
                                "POST"
                            ],
                            "request" => ( object ) [
                                "language" => "en"
                            ]
                        ],
                    ]);
                    $namespace = 'Domain' . '\\' . str_replace('.', '_', $url[$index_read]->name) . '\\' . 'Controller';
                    $dir_domain = $object->config('project.dir.domain') .
                        $url[$index_read]->name .
                        $object->config('ds')
                    ;
                    $dir_domain_controller = $dir_domain .
                        'Controller' .
                        $object->config('ds')
                    ;
                    $command = Core::binary($object) . ' r3m_io/account setup user -namespace=' . escapeshellarg($namespace) .
                        ' -dir=' . escapeshellarg($dir_domain_controller)
                    ;
                    ob_start();
                    Core::execute($object, $command, $output, $notification);
                    if(!empty($output)){
                        echo rtrim($output, PHP_EOL) . PHP_EOL;
                    }
                    $ob = ob_get_clean();
                    if(empty($output) && !empty($ob)){
                        echo rtrim($ob, PHP_EOL) . PHP_EOL;
                    }
                    if(!empty($notification)){
                        echo rtrim($notification, PHP_EOL) . PHP_EOL;
                    }

                    /*
                    $dir_domain = $object->config('project.dir.domain') .
                        $url[$index_read]->name .
                        $object->config('ds')
                    ;
                    $dir_domain_controller = $dir_domain .
                        'Controller' .
                        $object->config('ds')
                    ;
                    $dir_template = $object->config('project.dir.package') .
                        'R3m' .
                        $object->config('ds') .
                        'Io' .
                        $object->config('ds') .
                        'Account' .
                        $object->config('ds') .
                        'Data' .
                        $object->config('ds') .
                        'Php' .
                        $object->config('ds')
                    ;
                    $url_template = $dir_template .
                        'User' .
                        $object->config('extension.php') .
                        $object->config('extension.tpl')
                    ;
                    if(!Dir::exist($dir_domain_controller)){
                        Dir::create($dir_domain_controller, Dir::CHMOD);
                    }
                    if(File::exist($url_template)){
                        $content = File::read($url_template);
                        $url_controller = $dir_domain_controller . 'User.php';
                        File::write($url_controller, $content);
                    }
*/
                    
                    //file from r3m_io/account needs to go to /Application/Domain/{name}/Controller/User.php
                }
            }
            elseif($index_read) {
                $namespace = 'Domain' . '\\' . str_replace('.', '_', $url[$index_read]->name) . '\\' . 'Controller';
                $dir_domain = $object->config('project.dir.domain') .
                    $url[$index_read]->name .
                    $object->config('ds')
                ;
                $dir_domain_controller = $dir_domain .
                    'Controller' .
                    $object->config('ds')
                ;
                $command = Core::binary($object) . ' r3m_io/account setup user -namespace=' . escapeshellarg($namespace) .
                    ' -dir=' . escapeshellarg($dir_domain_controller) .
                    ' -patch'
                ;
                $mode = $object->config('core.execute.mode');
                $object->config('core.execute.mode', 'stream');
                Core::execute($object, $command, $output, $notification);
                if(!empty($output)){
                    echo rtrim($output, PHP_EOL) . PHP_EOL;
                }
                if(!empty($notification)){
                    echo rtrim($notification, PHP_EOL) . PHP_EOL;
                }
                if($mode){
                    $object->config('core.execute.mode', $mode);
                } else {
                    $object->config('delete', 'core.execute.mode');
                }
            }
        } else {
            throw new Exception('No hosts found !' . PHP_EOL);
        }
        $email = Cli::read('input', 'email: ');
        $password = Cli::read('input-hidden', 'password: ');


        $route = $node->record(
            'System.Route',
            $node->role_system(),
            [
                'filter' => [
                    'host' => strtolower($url[$index_read]->name),
                    'name' => 'user-login'
                ]
            ]
        );
        if($route){
            if(
                property_exists($url[$index_read], 'url') &&
                property_exists($url[$index_read]->url, $object->config('framework.environment'))
            ){
                $path = $route['node']->path;
                if(substr($path, 0, 1) === $object->config('ds')){
                    $path = substr($path, 1);
                }
                $login_url = $url[$index_read]->url->{$object->config('framework.environment')} . $route['node']->path;
                $login_method = 'POST';
                if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                    $client = new Client([
                        'timeout'  => 10.0,
                        'verify' => false
                    ]);

                } else {
                    $client = new Client([
                        'timeout'  => 30.0,
                    ]);
                }
                $response = $client->request(
                    $login_method,
                    $login_url,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ],
                        'form_params' => [
                            'email' => $email,
                            'password' => $password
                        ]
                    ]
                );
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                d($statusCode);
                ddd($body);

                    // Now you can access your data from the $json array.
                    // For example, to get a value associated with a key 'key3' in the JSON response:
                    // $value = $json['key3'];

                /*
                } catch (RequestException | GuzzleException $e) {
                    echo Psr7\Message::toString($e->getRequest());
                    if ($e->hasResponse()) {
                        echo Psr7\Message::toString($e->getResponse());
                    }
                }
                */
            }
        }
//        d($route);

//        ddd($url[$index_read]);

        die('endtest');

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