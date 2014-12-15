<?php
// This Plugin is Made by DeBe (hu6677@naver.com)
namespace DeBePlugins\DropCount;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\block\Block;

class DropCount extends PluginBase implements Listener{

	public function onEnable(){
		$this->item = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$rm = TextFormat::RED . "Usage: /DropCount ";
		$mm = "[DropCount] ";
		$ik = $this->isKorean();
		$set = $this->set;
		if(!isset($sub[0])){
			$r = $rm . ($ik ? "<횟수1> <횟수2>": "<Num1> <Num2>");
		}else{
			if($sub[0] < 0 || !is_numeric($sub[0])) $sub[0] = 0;
			$sub[0] = round($sub[0]);
			if(isset($sub[1]) && $sub[1] > $sub[0] && is_numeric($sub[1]) !== false) $sub[0] = $sub[0] . "~" . round($sub[1]);
			$set["Count"] = $sub[0];
			$r = $mm . ($ik ? "드롭 횟수가 $sub[0] 로 설정되었습니다.": "Drop count is set $sub[0]");
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->set !== $set){
			$this->set = $set;
			$this->saveYml();
		}
		return true;
	}

	public function onBlockBreak(BlockBreakEvent $event){
		if($event->isCancelled()) return;
		$b = $event->getBlock();
		foreach($b->getDrops($event->getItem()) as $i){
			$this->item[$i[0] . ":" . $i[1] . ":" . $i[2]] = true;
			for($for = 0; $for < $this->getCount(); $for++){
				$b->getLevel()->dropItem($b, Item::get($i[0], $i[1], $i[2]));
			}
		}
	}

	public function onItemSpawn(ItemSpawnEvent $event){
		$entity = $event->getEntity();
		$i = $entity->getItem();
		$item = $i->getID() . ":" . $i->getDamage() . ":" . $i->getCount();
		if(isset($this->item[$item])){
			unset($this->item[$item]);
			$entity->close();
		}
	}

	public function getCount(){
		$c = explode("~", $this->set["Count"]);
		if(isset($c[1])){
			$cnt = rand($c[0], $c[1]);
		}else{
			$cnt = $c[0];
		}
		return $cnt;
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/");
		$this->set = (new Config($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/" . "DropCount.yml", Config::YAML, ["Count" => "1~1" ]))->getAll();
	}

	public function saveYml(){
		$set = new Config($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/" . "DropCount.yml", Config::YAML, ["Count" => "1~1" ]);
		$set->setAll($this->set);
		$set->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}