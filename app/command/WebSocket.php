<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\websocket\WebSocketServer;

class WebSocket extends Command
{
    protected function configure()
    {
        $this->setName('websocket')
            ->setDescription('Start WebSocket Server');
    }

    protected function execute(Input $input, Output $output)
    {
        $ws = new WebSocketServer();
        $ws->start();
    }
} 