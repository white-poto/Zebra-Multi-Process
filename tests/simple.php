<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 15-1-24
 * Time: 下午9:58
 */


define('SRC_ROOT', dirname(dirname(__FILE__)) . '/src/Jenner/Zebra/MultiProcess') ;

require SRC_ROOT . DIRECTORY_SEPARATOR . 'ProcessManager.php';
require SRC_ROOT . DIRECTORY_SEPARATOR . 'Process.php';


declare(ticks=1); // This part is critical, be sure to include it
$manager = new \Jenner\Zebra\MultiProcess\ProcessManager();
$manager->fork(new \Jenner\Zebra\MultiProcess\Process(function() { sleep(5); }, "My super cool process"), 'mysql', 'mysql');

do
{
    foreach($manager->getChildren() as $process)
    {
        $iid = $process->getInternalId();
        if($process->isAlive())
        {
            echo sprintf('Process %s is running', $iid);
        } else if($process->isFinished()) {
            echo sprintf('Process %s is finished', $iid);
        }
        echo "\n";
    }
    sleep(1);
} while($manager->countAliveChildren());

