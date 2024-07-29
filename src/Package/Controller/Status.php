<?php

namespace Package\R3m\Io\Task\Controller;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\File;

use Exception;

class Status extends Controller {
    const DIR = __DIR__ . '/';
    const MODULE_INFO = 'Info';


    /**
     * @throws Exception
     */
    public static function youtube(App $object)
    {
        $timer = 0;
        $status_url = false;
        while(true){
            $timer++;
            if(File::exist($object->request('status.url'))){
                $status_url = $object->request('status.url');
                break;
            }
            sleep(1);
            if($timer > 10){
                echo 'Timeout' . PHP_EOL;
                break;
            }
        }
        if($status_url){
            $timer = 0;
            while(true){
                $read = File::read($status_url);
                if(str_contains($read, 'yt-dlp: not found')){
                    echo 'Please install the yt-dlp package' . PHP_EOL;
                    return;
                }
                d($read);
                sleep(1);
                if($timer > 10){
                    echo 'Timeout' . PHP_EOL;
                    break;
                }
            }

        }
    }
}