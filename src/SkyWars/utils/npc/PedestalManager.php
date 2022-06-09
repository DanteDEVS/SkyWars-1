<?php

declare(strict_types = 1);

namespace SkyWars\utils\npc;

use SkyWars\database\SkyWarsDatabase;
use SkyWars\MCSkyWars;
use SkyWars\utils\PlayerData;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use poggit\libasynql\SqlError;

/**
 * Manages pedestal entities and watch for player events update.
 */
class PedestalManager extends Task implements Listener {

	/** @var FakeHuman[] */
	private $npcLocations = [];
	/** @var Level */
	private $level;
	/** @var array<int, array<string|int>> */
	private $totalResult = [];
	/** @var bool */
	private $isFetching = false;

	/**
	 * @param Vector3[] $vectors
	 * @param Level $level
	 */
	public function __construct(array $vectors, Level $level){
		$this->fetchData(function() use ($vectors, $level): void{
			foreach($vectors as $key => $vec){
				$entity = new FakeHuman($level, Entity::createBaseNBT($vec), $key + 1);
				$entity->spawnToAll();

				$this->npcLocations[] = $entity;
			}

			$this->level = $level;

			MCSkyWars::getInstance()->getScheduler()->scheduleRepeatingTask($this, 16 * 20);
		});
	}

	public function closeAll(): void{
		foreach($this->npcLocations as $npc){
			$npc->close();
		}
	}

	/**
	 * @param EntityLevelChangeEvent $event
	 * @priority HIGH
	 */
	public function onPlayerLevelChange(EntityLevelChangeEvent $event): void{
		$pl = $event->getEntity();

		if($pl instanceof Player){
			if($event->getOrigin()->getFolderName() === $this->level->getFolderName()){
				$this->despawnFrom($pl);
			}elseif($event->getTarget()->getFolderName() === $this->level->getFolderName()){
				$this->spawnTo($pl);
			}
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority HIGH
	 */
	public function onPlayerJoinEvent(PlayerJoinEvent $event): void{
		if($event->getPlayer()->getLevel()->getFolderName() === $this->level->getFolderName()){
			$this->spawnTo($event->getPlayer());
		}
	}

	private function spawnTo(Player $player): void{
		foreach($this->npcLocations as $npc){
			$npc->spawnTo($player);
		}
	}

	private function despawnFrom(Player $player): void{
		foreach($this->npcLocations as $npc){
			$npc->despawnFrom($player);
		}
	}

	public function onRun(int $currentTick): void{
		$this->fetchData();
	}

	/**
	 * Retrieves pedestal information from the given level, 1-5.
	 * It will return null if the operation was faulty.
	 *
	 * @param int $level
	 * @return array<int, string|int>|null
	 */
	public function getPedestalObject(int $level): ?array{
		return $this->totalResult[$level - 1] ?? null;
	}

	private function fetchData(?callable $onComplete = null): void{
		if($this->isFetching) return;
		$this->isFetching = true;

		SkyWarsDatabase::getEntries(function(?array $players) use ($onComplete): void{
			// Avoid nulls and other consequences
			$player = [
				"Example-1" => 0,
				"Example-2" => 0,
				"Example-3" => 0,
			];

			/** @var PlayerData $value */
			foreach($players as $value){
				$player[$value->player] = $value->wins;
			}

			arsort($player);

			// Filter the element in an array that are not "Example"
			$filter = array_filter(array_keys($player), function($value): bool{
				return substr($value, 0, -2) !== "Example";
			});

			// Then we fetch the player's kills in the array object.
			$result = [];
			foreach($filter as $playerObject){
				$result[$playerObject] = $player[$playerObject];
			}

			// If the amount of filtered keys doesn't reach minimum requirements (Usually when SWFPE is freshly installed)
			// We merge the example with the first original result.
			if(count($filter) < 3) $result = array_merge($result, $player);

			foreach($result as $playerName => $kills){
				$this->totalResult[] = [$playerName, $kills];
			}

			if($onComplete !== null) $onComplete();

			$this->isFetching = false;
		}, function(SqlError $error): void{
			$this->isFetching = false;
		});
	}
}
