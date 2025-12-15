<?php

namespace AppKit\StartStop;

use AppKit\StartStop\Exception\StartStopException;

use AppKit\Async\Exception\CanceledException;
use function AppKit\Async\throwIfCanceled;

use Throwable;

class StartStopSequence {
    private $log;
    private $modules;
    
    function __construct($log) {
        $this -> log = $log -> withModule($this);
        $this -> modules = [];
    }
    
    public function start() {
        try {
            for($i = 0; $i < count($this -> modules); $i++) {
                $moduleName = get_class($this -> modules[$i]['module']);
                $this -> log -> debug("Starting module $moduleName...");
                try {
                    $this -> modules[$i]['module'] -> start();
                    $this -> modules[$i]['started'] = true;
                } catch(CanceledException $e) {
                    $this -> log -> debug("Start of module $moduleName has been canceled");
                    throw $e;
                } catch(Throwable $e) {
                    $error = "Failed to start module $moduleName";
                    $this -> log -> error($error, $e);
                    throw new StartStopException(
                        $error,
                        previous: $e
                    );
                }

                throwIfCanceled();
            }
        } catch(CanceledException $e) {
            $this -> log -> debug('Start sequence has been canceled');
            throw $e;
        }
    }
    
    public function stop() {
        for($i = count($this -> modules) - 1; $i >= 0; $i--) {
            if($this -> modules[$i]['started']) {
                $moduleName = get_class($this -> modules[$i]['module']);
                $this -> log -> debug("Stopping module $moduleName...");
                try {
                    $this -> modules[$i]['module'] -> stop();
                } catch(Throwable $e) {
                    $error = "Failed to stop module $moduleName";
                    $this -> log -> error($error, $e);
                    throw new StartStopException(
                        $error,
                        previous: $e
                    );
                }
            }
        }
    }

    public function addModule($module) {
        $this -> modules[] = [
            'module' => $module,
            'started' => false
        ];
        $this -> log -> debug('Registered module '.get_class($module));

        return $this;
    }
}
