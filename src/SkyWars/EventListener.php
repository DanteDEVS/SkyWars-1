<?php

declare(strict_types = 1);

namespace SkyWars;

use SkyWars\arena\api\Arena;
use SkyWars\arena\api\listener\BasicListener;
use SkyWars\arena\api\translation\TranslationContainer;
use SkyWars\database\SkyWarsDatabase;
use pocketmine\entity\Human;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

class EventListener extends BasicListener implements Listener {

	/** @var bool */
	public static $isDebug = false;
	/** @var bool[] */
	public static $moderators = [];

	/** @var int */
	private static $nextPlayer = 0;

	/** @var MCSkyWars */
	private $plugin;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;

		// My project environment.
		self::$isDebug = getenv("Project") === "E:\ACD-HyruleServer\plugins";
	}

	/**
	 * @param PlayerJoinEvent $e
	 *
	 * @priority MONITOR
	 */
	public function onPlayerLogin(PlayerJoinEvent $e): void{
		SkyWarsDatabase::createPlayer($e->getPlayer());
	}

	public function loginEvent(DataPacketReceiveEvent $event): void{
		// DEBUGGING PURPOSES

		if(!self::$isDebug) return;

		$packet = $event->getPacket();

		if($packet instanceof LoginPacket){
			$packet->username = "larryZ00" . self::$nextPlayer++;
			$packet->clientUUID = UUID::fromRandom()->toString();
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority HIGHEST
	 */
	public function playerJoinEvent(PlayerJoinEvent $event): void{
		$player = $event->getPlayer();

		if($player->hasPermission("sw.moderation") && !isset($this->moderators[$player->getName()])){
			$player->sendMessage(TranslationContainer::getTranslation($player, 'moderation-pre-enable'));

			self::$moderators[$player->getName()] = true;
		}
	}

	/**
	 * @param PlayerChatEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerChatEvent(PlayerChatEvent $event): void{
		parent::onPlayerChatEvent($event);

		// Filter player messages from getting sent to players in arena.
		$player = $event->getPlayer();
		if(($arena = $this->getArena($player)) === null){
			$filterRecipients = [];
			foreach($event->getRecipients() as $sender){
				if(!($sender instanceof Player)){
					continue;
				}

				if($this->getArena($sender) === null){
					$filterRecipients[] = $sender;
				}
			}

			$event->setRecipients($filterRecipients);
		}else{
			foreach(self::$moderators as $playerName => $indicator){
				$moderator = Server::getInstance()->getPlayer($playerName);

				if($indicator && $moderator !== null && $moderator->isOnline() && $playerName !== $player->getName()){
					$moderator->sendMessage(TextFormat::GRAY . $arena->getMapName() . " > " . $event->getFormat());
				}
			}
		}
	}

	public function getArena(Human $player): ?Arena{
		$arenas = $this->plugin->getArenaManager()->getArenas();
		foreach($arenas as $arena){
			if($arena->getPlayerManager()->isInArena($player)){
				return $arena;
			}
		}

		return null;
	}
}
