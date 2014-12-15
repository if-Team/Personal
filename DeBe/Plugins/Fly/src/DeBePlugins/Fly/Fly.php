<?php
// This Plugin is Made by DeBe (hu6677@naver.com)
namespace DeBePlugins\Fly;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerKickEvent;

class Fly extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$ik = $this->isKorean();
		$fly = $this->fly;
		if($fly["Fly"]) $b = false;
		else $b = true;
		$fly["Fly"] = $b;
		$m = "[Fly] " . ($ik ? "플라이킥을 " : "Fly kick is ") . ($b ? ($ik ? "켭니다." : "On") : ($ik ? "끕니다." : "Off"));
		if($this->fly !== $fly){
			$this->fly = $fly;
			$this->saveYml();
		}
		$this->getServer()->broadCastMessage($m);
		return true;
	}

	public function onPlayerKick(PlayerKickEvent $event){
		if($this->fly["Fly"] && $event->getReason() == "Flying is not enabled on this server") $event->setCancelled();
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/");
		$this->fly = (new Config($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/" . "Fly.yml", Config::YAML,["Fly" => true]))->getAll();
	}

	public function saveYml(){
		$fly = new Config($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/" . "Fly.yml", Config::YAML);
		$fly->setAll($this->fly);
		$fly->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! DeBePlugins/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}