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
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener{

	private $lastInteract = [];

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(Item::getCreativeItemIndex(Item::get(Item::GOLDEN_PICKAXE)) === -1){
			Item::addCreativeItem(Item::get(Item::GOLDEN_PICKAXE));
		}
	}

	public function onDisable(){
		Item::removeCreativeItem(Item::get(Item::GOLDEN_PICKAXE));
	}

	private function getLastAction(Player $player){
		return isset($this->lastInteract[$player->getId()]) ? $this->lastInteract[$player->getId()] : null;
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @priority HIGHEST
	 * @ignoreCancelled false
	 */
	public function onInteract(PlayerInteractEvent $event){
		$this->lastInteract[$event->getPlayer()->getId()] = [$event->getAction(), $event->getFace()];

		if(!$event->isCancelled() and $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			$item = clone $event->getItem();
			$block = $event->getBlock();
			if($item->isPickaxe() and $item->getId() === Item::GOLD_PICKAXE){
				$blockItem = Item::get($block->getId(), $block->getDamage());
				$result = $this->getServer()->getCraftingManager()->matchFurnaceRecipe($blockItem);

				if($result === null){ //Search for results
					foreach($this->getServer()->getCraftingManager()->getFurnaceRecipes() as $recipe){
						if($recipe->getResult()->equals($blockItem)){
							$result = $recipe;
							break;
						}
					}
				}

				if($this->isFortuneBlock($block->getId())){
					$fakeItem = Item::get(Item::DIAMOND_PICKAXE);
					$drops = $block->getDrops($fakeItem);
					if($block->getLevel()->useBreakOn($block, $fakeItem, null)){
						foreach($drops as $d){
							if(mt_rand(0, 100) < 90){
								$block->getLevel()->dropItem($block->add(0.5, 0.5, 0.5), Item::get($d[0], $d[1], $d[2]));
							}else{
								$block->getLevel()->dropItem($block->add(0.5, 0.5, 0.5), Item::get($d[0], $d[1], $d[2] * 2));
							}
						}
					}

					$item->useOn($block);
					if($item->getDamage() >= $item->getMaxDurability()){
						$item = Item::get(Item::AIR, 0, 0);
					}

					$event->getPlayer()->getInventory()->setItemInHand($item);
				}elseif($this->isPickaxeBlock($block->getId()) or $result !== null){
					if($result !== null){
						$block->getLevel()->setBlock($block, Block::get(0));
						$block->getLevel()->addParticle(new DestroyBlockParticle($block->add(0.5, 0.5, 0.5), $block));
						for($i = 0; $i < 27; ++$i){ //Flame particles distributed inside the block, and offset them randomly
							$xOff = ($i % 3 - 1) / 3 + mt_rand(-1, 1) / 5;
							$yOff = (floor($i / 3) % 3 - 1) / 3 + mt_rand(-1, 1) / 5;
							$zOff = (floor($i / 9) - 1) / 3 + mt_rand(-1, 1) / 5;
							$block->getLevel()->addParticle(new FlameParticle($block->add(0.5 + $xOff, 0.5 + $yOff, 0.5 + $zOff), $block));
						}
						$block->getLevel()->addSound(new FizzSound($block->add(0.5, 0.5, 0.5)));
						$block->getLevel()->dropItem($block->add(0.5, 0.5, 0.5), $result->getResult());
					}else{
						$block->getLevel()->useBreakOn($block, $item, null);
					}

					$item->useOn($block);
					if($item->getDamage() >= $item->getMaxDurability()){
						$item = Item::get(Item::AIR, 0, 0);
					}

					$event->getPlayer()->getInventory()->setItemInHand($item);
				}
			}
		}
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
					new Vector3($pos->x - 1, $pos->y, $pos->z - 1)
				];

			case Vector3::SIDE_EAST:
			case Vector3::SIDE_WEST:
				return [
					new Vector3($pos->x, $pos->y + 1, $pos->z + 1),
					new Vector3($pos->x, $pos->y, $pos->z + 1),
					new Vector3($pos->x, $pos->y - 1, $pos->z + 1),

					new Vector3($pos->x, $pos->y + 1, $pos->z),
					new Vector3($pos->x, $pos->y - 1, $pos->z),

					new Vector3($pos->x, $pos->y + 1, $pos->z - 1),
					new Vector3($pos->x, $pos->y, $pos->z - 1),
					new Vector3($pos->x, $pos->y - 1, $pos->z - 1)
				];

			case Vector3::SIDE_SOUTH:
			case Vector3::SIDE_NORTH:
				return [
					new Vector3($pos->x + 1, $pos->y + 1, $pos->z),
					new Vector3($pos->x + 1, $pos->y, $pos->z),
					new Vector3($pos->x + 1, $pos->y - 1, $pos->z),

					new Vector3($pos->x, $pos->y + 1, $pos->z),
					new Vector3($pos->x, $pos->y - 1, $pos->z),

					new Vector3($pos->x - 1, $pos->y + 1, $pos->z),
					new Vector3($pos->x - 1, $pos->y, $pos->z),
					new Vector3($pos->x - 1, $pos->y - 1, $pos->z)
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

	private function isFortuneBlock($blockId){
		return $blockId === Block::COAL_ORE or
		$blockId === Block::LAPIS_ORE or
		$blockId === Block::DIAMOND_ORE or
		$blockId === Block::REDSTONE_ORE or
		$blockId === Block::LIT_REDSTONE_ORE or
		$blockId === Block::EMERALD_ORE;
	}
}