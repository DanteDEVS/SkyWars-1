<?php

declare(strict_types = 1);

namespace SkyWars\forms;

use pocketmine\Player;

class FormQueue {

	/** @var true[] */
	private static $players = [];

	public static function sendForm(Player $player, Form $form): void{
		if(isset(self::$players[$player->getName()])){
			return;
		}

		$player->sendForm($form);

		self::$players[$player->getName()] = true;
	}

	public static function removeForm(Player $player): void{
		unset(self::$players[$player->getName()]);
	}
}
