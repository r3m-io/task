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
        $create = $node->create(
            'Task',
            $node->role_system(),
            $options
        );
        ddd($create);

        if(property_exists($options, 'description')){
            if(is_array($options->description)){
                $node->set('description', implode(PHP_EOL, $options->description));
            } else {
                $node->set('description', $options->description);
            }
        }
        if(
            property_exists($options, 'options') &&
            property_exists($options->options, 'command')
        ){
            if(is_array($options->options->command)){
                $node->set('options.command', $options->options->command);
            } elseif(is_scalar($options->options->command)){
                $node->set('options.command', [ $options->options->command ]);
            }
        }
        $node->set('options.status', 'queue');
        $node->set('options.priority', 100);



        return $node->data();
    }
}