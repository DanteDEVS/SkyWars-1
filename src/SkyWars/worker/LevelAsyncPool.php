<?php

declare(strict_types = 1);

namespace SkyWars\worker;

use SkyWars\Main;
use pocketmine\scheduler\AsyncPool;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\GarbageCollectionTask;
use pocketmine\Server;
use pocketmine\utils\MainLogger;

// According to dylan, never perform IO operations in PMMP async pool,
// since it will lag the server if the server enables packet compression.
class LevelAsyncPool extends AsyncPool {

	/** @var LevelAsyncPool */
	private static $instance;

	public function __construct(MCSkyWars $plugin, int $workerSize){
		parent::__construct(Server::getInstance(), $workerSize, 255, Server::getInstance()->getLoader(), MainLogger::getLogger());

		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick): void{
			if(($w = $this->shutdownUnusedWorkers()) > 0){
				MainLogger::getLogger()->debug("Shut down $w idle async pool workers");
			}
			foreach($this->getRunningWorkers() as $i){
				$this->submitTaskToWorker(new GarbageCollectionTask(), $i);
			}
		}), 30 * 60 * 20);

		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick): void{
			$this->collectTasks();
		}), 1);

		self::$instance = $this;
	}

	public static function getAsyncPool(): LevelAsyncPool{
		return self::$instance;
	}
}
