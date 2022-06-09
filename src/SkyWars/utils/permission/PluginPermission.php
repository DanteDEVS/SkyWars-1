<?php

declare(strict_types = 1);

namespace SkyWars\utils\permission;

use SkyWars\arena\api\utils\SingletonTrait;
use SkyWars\database\SkyWarsDatabase;
use SkyWars\MCSkyWars;
use SkyWars\utils\PlayerData;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\permission\PermissionAttachment;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;

class PluginPermission implements Listener {
	use SingletonTrait;

	/** @var PermissionAttachment[] */
	private $attachments;

	public function __construct(){
		$this->attachments = [];
	}

	public function addPermission(Player $player, string $permission): void{
		$attachment = $this->attachments[$player->getName()] ?? null;
		if($attachment === null){
			throw new AssumptionFailedError("Player permission attachment should be initialized in the first place.");
		}

		$attachment->setPermission($permission, true);
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerJoinEvent(PlayerJoinEvent $event): void{
		$player = $event->getPlayer();

		$this->attachments[$player->getName()] = $player->addAttachment(MCSkyWars::getInstance());

		SkyWarsDatabase::getPlayerEntry($player, function(?PlayerData $result) use ($player){
			if(!isset($this->attachments[$player->getName()]) || $result === null) return;

			foreach($result->permissions as $permission){
				$this->attachments[$player->getName()]->setPermission($permission, true);
			}
		});
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerQuitEvent(PlayerQuitEvent $event): void{
		unset($this->attachments[$event->getPlayer()->getName()]);
	}
}
