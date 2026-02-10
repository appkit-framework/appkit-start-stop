<?php

namespace AppKit\StartStop;

use AppKit\Async\CanceledException;
use function AppKit\Async\throwIfCanceled;

use Throwable;

class StartStopSequence {
    private $log;
    private $modules;
    
    function __construct($log) {
        $this -> log = $log -> withModule(static::class);
        $this -> modules = [];
    }
    
    public function start() {
        for($i = 0; $i < count($this -> modules); $i++) {
            throwIfCanceled();

            $moduleName = get_class($this -> modules[$i]['module']);
            $context = [ 'module' => $moduleName ];

            $this -> log -> debug('Starting module', $context);
            try {
                $this -> modules[$i]['module'] -> start();
                $this -> modules[$i]['started'] = true;
                $this -> log -> info('Started module', $context);
            } catch(CanceledException $e) {
                $this -> log -> info('Module start canceled', $context);
                throw $e;
            } catch(Throwable $e) {
                $error = 'Failed to start module';
                $this -> log -> error($error, $context, $e);
                throw new StartStopException(
                    "$error $moduleName",
                    previous: $e
                );
            }
        }
    }
    
    public function stop() {
        for($i = count($this -> modules) - 1; $i >= 0; $i--) {
            if($this -> modules[$i]['started']) {
                $moduleName = get_class($this -> modules[$i]['module']);
                $context = [ 'module' => $moduleName ];

                $this -> log -> debug('Stopping module', $context);
                try {
                    $this -> modules[$i]['module'] -> stop();
                    $this -> log -> info('Stopped module', $context);
                } catch(Throwable $e) {
                    $error = 'Failed to stop module';
                    $this -> log -> error($error, $context, $e);
                    throw new StartStopException(
                        "$error $moduleName",
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
        $this -> log -> debug(
            'Registered module',
            [ 'module' => get_class($module) ]
        );

        return $this;
    }
}
