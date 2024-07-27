<?php
namespace Package\R3m\Io\Task\Service;

class Task
{
    const NODE = 'Task';
    const OPTIONS_STATUS_QUEUE = 'queue';
    const OPTIONS_STATUS_DONE = 'done';
    const OPTIONS_STATUS_ERROR = 'error';
    const OPTIONS_STATUS_RUNNING = 'running';
    const OPTIONS_STATUS_WAITING = 'waiting';

    const OPTIONS_PRIORITY_DEFAULT = 100;
}