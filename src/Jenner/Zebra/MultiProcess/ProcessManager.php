<?php
/**
 * Created by PhpStorm.
 * User: huyanping
 * Date: 14-8-24
 * Time: 下午14:28
 *
 */

namespace Jenner\Zebra\MultiProcess;
declare(ticks = 1);

class RuntimeException extends \Exception
{
}

class ProcessManager
{
    /** @var array|Process */
    protected $children = array();

    protected $shmPerChildren;
    protected $allocateSHMPerChildren = false;

    protected $cleanupOnShutdown = false;

    public function __construct()
    {
        $this->setup();
    }

    public function cleanup()
    {
        foreach ($this->children as $process) {
            $segment = $process->getShmSegment();
            if ($segment) {
                $segment->destroy();
            }
        }
    }

    public function cleanupOnShutdown()
    {
        if (!$this->cleanupOnShutdown) {
            $this->cleanupOnShutdown = true;
            register_shutdown_function(array($this, 'cleanup'));
        }
    }

    protected function setup()
    {
        pcntl_signal(SIGCHLD, function ($signal) {
            while (($pId = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                $this->getChildByPID($pId)->setFinished(true, $status);
            }
        });

        $exit = function () {
            exit;
        };

        //pcntl_signal(SIGTERM, $exit);
        //pcntl_signal(SIGINT,  $exit);
    }

    public function fork(Process $children, $user_name = null, $group_name = null)
    {
        if ($this->allocateSHMPerChildren()) {
            $children->setSHMSegment(new SHMCache(uniqid('process_manager;shm_per_children' . $children->getInternalId()), $this->allocateSHMPerChildren));
        }

        pcntl_sigprocmask(SIG_BLOCK, array(SIGCHLD));
        $pid = pcntl_fork();
        // Error
        if ($pid == -1) {
            throw new RuntimeException('pcntl_fork() returned -1, are you sure you are running the script from CLI?');
        } // Child process
        else if (!$pid) {
            if(!is_null($group_name)){
                if(!$this->setGroup($group_name)){
                    throw new RuntimeException('set group_name failed. are you sure you have the privileges?');
                }
            }
            if(!is_null($user_name)){
                if(!$this->setUser($user_name))
                    throw new RuntimeException('set user_name failed. are you sure you have the privileges?');

            }
            $children->run();
            exit; // redundant, added only for clarity
        } // Main process
        else {
            $children->setStarted(true);

            $this->children[] = $children;

            // Store the children's PID
            $children->setPid($pid);
            pcntl_sigprocmask(SIG_UNBLOCK, array(SIGCHLD));
        }
    }

    /**
     * @param int $pid
     * @return Process
     */
    public function getChildByPID($pid)
    {
        foreach ($this->children as $process) {
            if ($process->getPid() == $pid) {
                return $process;
            }
        }

        return null;
    }

    /**
     * @param $InternalId
     * @return Process
     */
    public function getChildByInternalId($InternalId)
    {
        foreach ($this->children as $process) {
            if ($process->getInternalId() == $InternalId) {
                return $process;
            }
        }

        return null;
    }

    public function countAliveChildren()
    {
        $alive = 0;
        foreach ($this->children as $process) {
            /** @var $process Process */
            if ($process->isAlive()) {
                ++$alive;
            }
        }

        return $alive;
    }

    /**
     * @return array|Process
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function allocateSHMPerChildren($bytes = null)
    {
        if ($bytes !== null) {
            $this->allocateSHMPerChildren = $bytes;
        }
        return $this->allocateSHMPerChildren;
    }

    public function setUser($user_name)
    {
        $user_info = posix_getpwnam($user_name);
        $user_id = $user_info["uid"];

        return posix_setuid($user_id);
    }

    public function setGroup($group_name)
    {
        $group_info = posix_getgrnam($group_name);
        $group_id = $group_info['gid'];

        return posix_setgid($group_id);
    }

}

