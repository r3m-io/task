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
            $read = false;
            $previous = null;
            $release_timer = false;
            $last_known_size = 0;
            $previous_size = 0;
            $avg_speed = [];
            while(true){
                $read = File::read($status_url);
                if(
                    $previous === $read &&
                    $release_timer === false
                ){
                    $timer++;
                } else {
                    $timer = 0;
                }
                if(str_contains($read, 'yt-dlp: not found')){
                    throw new Exception('Please install the yt-dlp package');
                }
                if(str_contains($read, '[download]')){
                    //release timer
                    $release_timer = true;
                }
                if(str_contains($read, '[finished]')){
                    //release timer
                    break;
                }
//                $content = explode(PHP_EOL, $read);
                if($release_timer){
                    $read_line =  File::tail($status_url);
                    //[download]   1.7% of  228.97MiB at    5.83MiB/s ETA 00:38
                    $percentage = null;
                    $download = null;
                    $download_format = null;
                    $size = null;
                    $size_format = null;
                    $speed = null;
                    $speed_format = null;
                    $eta = null;
                    $explode = explode('[download]', $read_line, 2);
                    if(array_key_exists(1, $explode)){
                        $explode = explode('%', $explode[1], 2);
                        if(array_key_exists(0, $explode)){
                            $percentage = (float) trim($explode[0]);
                            if(array_key_exists(1, $explode)){
                                $explode = explode('of', $explode[1], 2);
                                if(array_key_exists(1, $explode)){
                                    $explode = explode('at', $explode[1], 2);
                                    if(array_key_exists(0, $explode)){
                                        $size = trim($explode[0]);
                                        $size = File::size_calculation($size);
                                        if($size > $last_known_size){
                                            $last_known_size = $size;
                                        }
                                        $size_format = File::size_format($size);
                                        $download = $size * ($percentage / 100);
                                        $download_format = File::size_format($download);
                                    }
                                    if(array_key_exists(1, $explode)){
                                        $explode = explode('/s ETA', $explode[1], 2);
                                        if(array_key_exists(0, $explode)){
                                            $speed = trim($explode[0]);
                                            $speed = File::size_calculation($speed);
                                            $speed_format = File::size_format($speed) . '/s';
                                        }
                                        if(array_key_exists(1, $explode)) {
                                            $eta = trim($explode[1]);
                                        }
                                    }
                                }
                            }
                        }
                        $progress = (object) [
                            'percentage' => $percentage,
                            'download' => $download,
                            'download_format' => $download_format,
                            'size' => $size,
                            'size_format' => $size_format,
                            'speed' => $speed,
                            'speed_format' => $speed_format,
                            'eta' => $eta,
                            'read' => $read_line
                        ];
                        d($progress);
                        //progress needs to be added to the task through a patch
                    }
                    $explode =  explode('[ExtractAudio]', $read_line, 2);
                    if(array_key_exists(1, $explode)) {
                        $explode = explode('[download]', $read, 2);
                        $basename = false;
                        if (array_key_exists(1, $explode)) {
                            $explode = explode(':', $explode[1], 2);
                            if (array_key_exists(1, $explode)) {
                                $explode = explode(PHP_EOL, $explode[1]);
                                $basename = trim($explode[0]);
                            }
                        }
                        $tmp = explode('.', $basename);
                        $extension = array_pop($tmp);
                        $basename = implode('.', $tmp);
                        $target = $object->request('target_dir') .
                            $basename .
                            $object->config('extension.mp3');
                        $size_original = $last_known_size;
                        $size_original_format = File::size_format($size_original);
                        $size = 0;
                        clearstatcache();
                        if (File::exist($target)) {
                            $size = File::size($target);
                            $size_format = File::size_format($size);
                        }
                        if ($size_original > 0) {
                            $destination_percentage = round(($size / $size_original) * 100, 2);
                        } else {
                            $destination_percentage = 0;
                        }
                        $speed = $size - $previous_size;
                        $speed_format = File::size_format($speed) . '/s';
                        $avg_speed[] = $speed;
                        $eta = 'N/A';
                        $eta_format = 'N/A';
                        if ($speed > 0) {
                            $count = count($avg_speed);
                            $avg = array_sum($avg_speed) / $count;
                            if($avg > 0){
                                $eta = ($size_original - $size) / (array_sum($avg_speed) / $count);
                                $eta_format = File::time_format($eta);
                            }
                            if($count > 100){
                                //remember 100 steps to get an average
                                array_shift($avg_speed);
                            }
                        }
                        $progress = (object)[
                            'percentage' => 100,
                            'is_converting' => true,
                            'target' => $target,
                            'size' => $size_original,
                            'size_format' => $size_original_format,
                            'destination_size' => $size,
                            'destination_size_format' => $size_format,
                            'destination_percentage' => $destination_percentage,
                            'speed' => $speed,
                            'speed_format' => $speed_format,
                            'eta' => $eta,
                            'eta_format' => $eta_format,
                            'extension' => $extension,
                            'read' => $read_line
                        ];
                        $previous_size = $size;
                        d($progress);
                        //progress needs to be patched in the task
                    }
                }
                sleep(1);
                $previous = $read;
                if($timer > 300){
                    echo 'Timeout' . PHP_EOL;
                    break;
                }
            }
        }
    }
}