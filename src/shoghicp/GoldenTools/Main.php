<?php

/*
 * GoldenTools plugin
 * Copyright (C) 2015 Shoghi Cervantes
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/
namespace shoghicp\GoldenTools;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener{

	private $lastInteract = [];

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	private function getLastAction(Player $player){
		return isset($this->lastInteract[$player->getId()]) ? $this->lastInteract[$player->getId()] : null;
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @priority LOWEST
	 * @ignoreCancelled false
	 */
	public function onInteract(PlayerInteractEvent $event){
		$this->lastInteract[$event->getPlayer()->getId()] = [$event->getAction(), $event->getFace()];
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		unset($this->lastInteract[$event->getPlayer()->getId()]);
	}

	/**
	 * @param BlockBreakEvent $event
	 * @priority HIGH
	 * @ignoreCancelled true
	 */
	public function onBlockBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		if($player === null){
			return;
		}

		$item = clone $event->getItem();
		$block = $event->getBlock();

		if($item->isPickaxe() and $item->getId() === Item::GOLD_PICKAXE){
			if($this->isPickaxeBlock($block->getId())){
				$action = $this->getLastAction($player);
				if($action !== null and $action[0] === PlayerInteractEvent::LEFT_CLICK_BLOCK){
					$this->faceAreaBreak($player, $item, $block, $action[1]);
				}
			}
		}
	}

	private function getFaceVectors(Vector3 $pos, $face){
		switch($face){
			case Vector3::SIDE_DOWN:
			case Vector3::SIDE_UP:
				return [
					new Vector3($pos->x + 1, $pos->y, $pos->z + 1),
					new Vector3($pos->x, $pos->y, $pos->z + 1),
					new Vector3($pos->x - 1, $pos->y, $pos->z + 1),

					new Vector3($pos->x + 1, $pos->y, $pos->z),
					new Vector3($pos->x - 1, $pos->y, $pos->z),

					new Vector3($pos->x + 1, $pos->y, $pos->z - 1),
					new Vector3($pos->x, $pos->y, $pos->z - 1),
					new Vector3($pos->x - 1, $pos->y, $pos->z - 1),
				];

			default:
				return [];
		}
	}

	private function faceAreaBreak(Player $player, Item $item, Vector3 $pos, $face){
		foreach($this->getFaceVectors($pos, $face) as $target){
			$itemClone = clone $item;
			$block = $player->getLevel()->getBlock($target);
			if($item->isPickaxe()){
				if($this->isPickaxeBlock($block->getId())){
					$player->getLevel()->useBreakOn($target, $itemClone, null);
				}
			}
		}
	}

	private function isPickaxeBlock($blockId){
		return $blockId === Block::STONE or
		$blockId === Block::COBBLESTONE or
		$blockId === Block::COBBLESTONE or
		$blockId === Block::GOLD_ORE or
		$blockId === Block::IRON_ORE or
		$blockId === Block::COAL_ORE or
		$blockId === Block::LAPIS_ORE or
		$blockId === Block::DIAMOND_ORE or
		$blockId === Block::REDSTONE_ORE or
		$blockId === Block::GLOWING_REDSTONE_ORE or
		$blockId === Block::EMERALD_ORE or
		$blockId === Block::SANDSTONE or
		$blockId === Block::GOLD_BLOCK or
		$blockId === Block::IRON_BLOCK or
		$blockId === Block::COAL_BLOCK or
		$blockId === Block::LAPIS_BLOCK or
		$blockId === Block::DIAMOND_BLOCK or
		$blockId === Block::REDSTONE_BLOCK or
		$blockId === Block::EMERALD_BLOCK or
		$blockId === Block::BRICKS or
		$blockId === Block::MOSS_STONE or
		$blockId === Block::OBSIDIAN or
		$blockId === Block::NETHERRACK or
		$blockId === Block::GLOWSTONE or
		$blockId === Block::STONE_BRICKS or
		$blockId === Block::NETHER_BRICKS or
		$blockId === Block::END_STONE or
		$blockId === Block::QUARTZ_BLOCK or
		$blockId === Block::STAINED_HARDENED_CLAY or
		$blockId === Block::HARDENED_CLAY;
	}
}