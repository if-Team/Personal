<?php

/**  __    __       __    __
 * /�� ��_�� ��   /��  "-./ ��
 * �� ��  __   �� �� �� ��/����
 *  �� ��_�� �� _���� ��_�� ��_��
 *   ��/_/  ��/__/   ��/_/ ��/__/
 * ( *you can redistribute it and/or modify *) */
namespace SimpleArea;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\block\Block;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\level\LevelUnloadEvent;

class SimpleArea extends PluginBase implements Listener {
	private static $instance = null;
	public $config, $config_Data;
	public $db = [ ];
	public $make_Queue = [ ];
	public $delete_Queue = [ ];
	public $rent_Queue = [ ];
	public $player_pos = [ ];
	public $checkMove = [ ];
	public $economyAPI = null;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		if (self::$instance == null) self::$instance = $this;
		$this->config = new Config ( $this->getDataFolder () . "settings.yml", Config::YAML, [ 
				"default-home-size" => 20,
				"maximum-home-limit" => 1,
				"show-prevent-message" => true,
				"economy-enable" => true,
				"economy-home-price" => 5000,
				"economy-home-reward-price" => 2500,
				"default-prefix" => "[ ���� ]",
				"welcome-prefix" => "[ ȯ���޽��� ]",
				"default-wall-type" => 139,
				"default-protect-blocks" => [ 
						139 ] ] );
		$this->config_Data = $this->config->getAll ();
		
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()] = new SimpleArea_Database ( $this->getServer ()->getDataPath () . "worlds\\" . $level->getFolderName () . "\\protects.yml", $level, $this->config_Data ["default-wall-type"] );
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"autoSave" ] ), 2400 );
		
		if ($this->checkEconomyAPI ()) $this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_Data );
		$this->config->save ();
		$this->autoSave ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function autoSave() {
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()]->save ();
	}
	public function onLevelLoad(LevelLoadEvent $event) {
		$level = $event->getLevel ();
		$this->db [$level->getFolderName ()] = new SimpleArea_Database ( $this->getServer ()->getDataPath () . "worlds\\" . $level->getFolderName () . "\\", $level, $this->config_Data ["default-wall-type"] );
	}
	public function onLevelUnload(LevelUnloadEvent $event) {
		$this->db [$event->getLevel ()->getFolderName ()]->save ();
	}
	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] )) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, "�� ������ ���������� �����Ǿ��ֽ��ϴ�." );
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
					if ($this->checkShowPreventMessage ()) $this->alert ( $player, "�� ����� ����� �����Ǿ� �ֽ��ϴ�." );
					$event->setCancelled ();
				}
			}
		} else {
			if ($this->db [$block->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, "�� ������ ���������� �����Ǿ��ֽ��ϴ�. (*ȭ��Ʈ����)" );
				$event->setCancelled ();
				return;
			}
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if (isset ( $area ["resident"] [0] )) if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] ) == true) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, "�� ������ ���������� �����Ǿ��ֽ��ϴ�." );
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
					if ($this->checkShowPreventMessage ()) $this->alert ( $player, "�� ����� ����� �����Ǿ� �ֽ��ϴ�." );
					$event->setCancelled ();
				}
			}
			return;
		}
		if ($this->db [$block->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			if ($this->checkShowPreventMessage ()) $this->alert ( $player, "�� ������ ���������� �����Ǿ��ֽ��ϴ�. (*ȭ��Ʈ����)" );
			$event->setCancelled ();
			return;
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (isset ( $this->make_Queue [$event->getPlayer ()->getName ()] )) {
			if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] = $event->getBlock ()->getSide ( 0 );
				$this->message ( $event->getPlayer (), "pos1�� ���õǾ����ϴ�." );
				return;
			} else if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] = $event->getBlock ()->getSide ( 0 );
				$this->message ( $event->getPlayer (), "pos2�� ���õǾ����ϴ�." );
				$this->message ( $event->getPlayer (), "������ ����÷��� /sa make ��" );
				$this->message ( $event->getPlayer (), "�۾��� �����Ϸ��� /sa cancel �� ���ּ���." );
				return;
			}
		}
		
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		if ($block->getId () == Block::SIGN_POST or $block->getId () == Block::WALL_SIGN) return;
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if (isset ( $area ["resident"] [0] )) if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] ) == true) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
					$event->setCancelled ();
				}
			}
			return;
		}
		if ($this->db [$block->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			$event->setCancelled ();
			return;
		}
	}
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->player_pos [$player->getName ()] )) {
			$this->player_pos [$player->getName ()] ["x"] = ( int ) round ( $player->x );
			$this->player_pos [$player->getName ()] ["z"] = ( int ) round ( $player->z );
		} else {
			$dif = abs ( ( int ) round ( $player->x - $this->player_pos [$player->getName ()] ["x"] ) );
			$dif += abs ( ( int ) round ( $player->z - $this->player_pos [$player->getName ()] ["z"] ) );
			if ($dif > 3) {
				$this->player_pos [$player->getName ()] ["x"] = ( int ) round ( $player->x );
				$this->player_pos [$player->getName ()] ["z"] = ( int ) round ( $player->z );
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if ($area != null) {
					if (! isset ( $this->checkMove [$event->getPlayer ()->getName ()] )) {
						$this->checkMove [$event->getPlayer ()->getName ()] = $area ["ID"];
					} else {
						if ($this->checkMove [$event->getPlayer ()->getName ()] == $area ["ID"]) return;
					}
					if (isset ( $area ["resident"] [0] )) {
						if ($this->getServer ()->getOfflinePlayer ( $area ["resident"] [0] ) == null) return;
						if ($area ["resident"] [0] == $player->getName ()) {
							if ($this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
								$this->message ( $player, "���� ���� ���� ȯ���մϴ� !" );
							}
							$welcome = $this->db [$player->getLevel ()->getFolderName ()]->getWelcome ( $area ["ID"] );
							if ($welcome != null) {
								$this->message ( $player, $welcome, $this->config_Data ["welcome-prefix"] );
							} else {
								$this->message ( $player, "( /welcome ���� ȯ���޽��� �������� ! )" );
							}
							return;
						}
						if (! $this->getServer ()->getOfflinePlayer ( $area ["resident"] [0] )->isOp ()) $this->message ( $player, "�� ������ " . $area ["resident"] [0] . "���� �����Դϴ�." );
						$welcome = $this->db [$player->getLevel ()->getFolderName ()]->getWelcome ( $area ["ID"] );
						if ($welcome != null) $this->message ( $player, $welcome, $this->config_Data ["welcome-prefix"] );
					} else {
						$this->message ( $player, "�� ������ ���� �����մϴ�, ���� : " . $this->config_Data ["economy-home-price"] . " (/buyhome)" );
					}
					return;
				} else {
					if (isset ( $this->checkMove [$event->getPlayer ()->getName ()] )) unset ( $this->checkMove [$event->getPlayer ()->getName ()] );
					return;
				}
			}
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent) {
			if ($event->getEntity () instanceof Player) {
				$player = $event->getEntity ();
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if ($area != null) if (! $this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) $event->setCancelled ();
			}
			if ($event->getDamager () instanceof Player) {
				$player = $event->getDamager ();
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if ($area != null) if (! $this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) {
					$this->message ( $player, "�� �������� PVP�� ������ �ʽ��ϴ� !" );
					$event->setCancelled ();
				}
			}
		}
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! $player instanceof Player) {
			$this->alert ( $player, "�ΰ��� �������� ��밡�� �մϴ�" );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case "home" :
				if (isset ( $args [0] )) {
					$this->goHome ( $player, $args [0] );
				} else {
					$this->printHomeList ( $player );
				}
				break;
			case "sethome" :
				if ($this->checkHomeLimit ( $player )) {
					$this->SimpleArea ( $player );
				} else {
					$this->message ( $player, "���� �ִ�ġ ��ŭ �����ϰ��ֽ��ϴ� - �������Ұ� !" );
				}
				break;
			case "sellhome" :
				$this->sellHome ( $player );
				break;
			case "givehome" :
				if (isset ( $args [0] )) {
					$this->giveHome ( $player, $args [0] );
				} else {
					$this->giveHome ( $player );
				}
				break;
			case "buyhome" :
				$this->buyhome ( $player );
				break;
			case "homelist" :
				$this->homelist ( $player );
				break;
			case "rent" :
				if (isset ( $args [0] )) {
					$this->rent ( $player, $args [0] );
				} else {
					$this->rent ( $player );
				}
				break;
			case "invite" :
				if (isset ( $args [0] )) {
					$this->invite ( $player, $args [0] );
				} else {
					$this->message ( $player, "/invite <������> - ���� �����մϴ�." );
				}
				break;
			case "inviteout" :
				$this->inviteout ( $player );
				break;
			case "inviteclear" :
				$this->inviteclear ( $player );
				break;
			case "invitelist" :
				$this->invitelist ( $player );
				break;
			case "welcome" :
				if (isset ( $args [0] )) {
					$this->welcome ( $player, implode ( " ", $args ) );
				} else {
					$this->message ( $player, "/welcome <�޽���> - ȯ���޽����� �����մϴ�." );
				}
				break;
			case "yap" :
				$this->autoAreaSet ( $player );
				break;
			case "sa" :
				if (! isset ( $args [0] )) {
					$this->helpPage ( $player );
					return true;
				}
				switch (strtolower ( $args [0] )) {
					case "whiteworld" :
						$this->whiteWorld ( $player );
						break;
					case "make" :
						$this->protectArea ( $player );
						break;
					case "cancel" :
						if (isset ( $this->make_Queue [$player->getName ()] )) {
							unset ( $this->make_Queue [$player->getName ()] );
							$this->message ( $player, "������ ����߽��ϴ�." );
							return true;
						} else {
							$this->alert ( $player, "�������� ������ �����ϴ�." );
							return true;
						}
						break;
					case "delete" :
						$this->deleteHome ( $player );
						break;
					case "protect" :
						$this->protect ( $player );
						break;
					case "pvp" :
						$this->pvp ( $player );
						break;
					case "allow" :
						if (isset ( $args [1] )) {
							$this->allowBlock ( $player, $args [1] );
						} else {
							$this->alert ( $player, "/sa allow - Ư���� ����� �� ����" );
						}
						break;
					case "forbid" :
						if (isset ( $args [1] )) {
							$this->forbidBlock ( $player, $args [1] );
						} else {
							$this->alert ( $player, "/sa allow - Ư���� ������ �� ����" );
						}
						break;
					case "homelimit" :
						if (isset ( $args [1] )) {
							$this->homelimit ( $player, $args [1] );
						} else {
							$this->homelimit ( $player );
						}
						break;
					case "economy" :
						$this->enableEonomy ( $player );
						break;
					case "homeprice" :
						if (isset ( $args [1] )) {
							$this->homeprice ( $player, $args [1] );
						} else {
							$this->homeprice ( $player );
						}
						break;
					case "hometax" :
						if (isset ( $args [1] )) {
							$this->hometax ( $player, $args [1] );
						} else {
							$this->hometax ( $player );
						}
						break;
					case "fence" :
						if (isset ( $args [1] )) {
							$this->setFenceType ( $player, $args [1] );
						} else {
							$this->setFenceType ( $player );
						}
						break;
					case "message" :
						$this->IhatePreventMessage ( $player );
						break;
					case "help" :
						if (isset ( $args [1] )) {
							$this->helpPage ( $player, $args [1] );
						} else {
							$this->helpPage ( $player );
						}
						break;
					default :
						$this->helpPage ( $player );
						break;
				}
				break;
		}
		return true;
	}
	public function hometax(Player $player, $price = null) {
		if ($price == null) {
			if ($this->db [$player->getLevel ()->getFolderName ()]->hometaxEnable ()) {
				$this->message ( $player, "�ش� �ʿ� �������� Ȱ��ȭ �߽��ϴ� !" );
				$this->message ( $player, "( /sa hometax <���> ���� �������� �������� )" );
			} else {
				$this->message ( $player, "�ش� �ʿ� �������� ��Ȱ��ȭ �߽��ϴ� !" );
				$this->message ( $player, "( /sa hometax <���> ���� �������� �������� )" );
			}
			return true;
		}
		if (! is_numeric ( $price )) {
			$this->alert ( $player, "������ ����� ������ ���ڿ��� �մϴ�." );
			return false;
		}
		$this->db [$player->getLevel ()->getFolderName ()]->hometaxPrice ( $price );
		$this->message ( $player, "�ش� ���� �������� " . $price . "$�� �����߽��ϴ� !" );
		return true;
	}
	public function setFenceType(Player $player, $fenceType = null) {
		if ($fenceType == null) {
			$this->message ( $player, "/sa fence <����> - �λ����� ��Ÿ���� ������ �����մϴ� !" );
		}
		if (! is_numeric ( $fenceType )) {
			$this->alert ( $player, "��Ÿ�� ������ �ݵ�� ���ڿ����մϴ� !" );
			return false;
		}
		$this->config_Data ["default-wall-type"] = $fenceType;
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()]->changeWall ( $fenceType );
		$this->message ( $player, "��Ÿ���� " . $fenceType . "������ �����߽��ϴ� !" );
	}
	public function IhatePreventMessage(Player $player) {
		if ($this->config_Data ["show-prevent-message"] == true) {
			$this->config_Data ["show-prevent-message"] = false;
			$this->message ( $player, "�������� �����޽����� ��Ȱ��ȭ �߽��ϴ� ( �ٽ� �Է½� Ȱ��ȭ ! )" );
		} else {
			$this->config_Data ["show-prevent-message"] = true;
			$this->message ( $player, "�������� �����޽����� Ȱ��ȭ �߽��ϴ� ( �ٽ� �Է½� ��Ȱ��ȭ ! )" );
		}
	}
	public function homeprice(Player $player, $price = null) {
		if ($price == null) {
			$this->alert ( $player, "/sa homeprice <����> - �⺻������ ���� �� ������ ���� !" );
			return false;
		}
		if (! is_numeric ( $price )) {
			$this->alert ( $player, "/sa homeprice <����> - ������ ������ ���ڿ����մϴ� !" );
			return false;
		}
		$this->config_Data ["economy-home-price"] = $price;
		$this->config_Data ["economy-home-reward-price"] = $price / 2;
		$this->message ( $player, "�⺻������ ���� �� ������ " . $count . "$ �� �����߽��ϴ� !" );
		return true;
	}
	public function enableEonomy(Player $player) {
		if ($this->config_Data ["economy-enable"] == true) {
			$this->config_Data ["economy-enable"] = false;
			$this->message ( $player, "���ڳ�̸� ��Ȱ��ȭ �߽��ϴ� ( �ٽ� �Է½� Ȱ��ȭ ! )" );
		} else {
			$this->config_Data ["economy-enable"] = true;
			$this->message ( $player, "���ڳ�̸� Ȱ��ȭ �߽��ϴ� ( �ٽ� �Է½� ��Ȱ��ȭ ! )" );
		}
	}
	public function homelimit(Player $player, $count = null) {
		if ($count == null) {
			$this->alert ( $player, "/sa homelimit <����> - ���������� �� �ִ�ġ ����" );
			return false;
		}
		if (! is_numeric ( $count )) {
			$this->alert ( $player, "/sa homelimit <����> - ������ ������ ���ڿ����մϴ� !" );
			return false;
		}
		$this->config_Data ["maximum-home-limit"] = $count;
		$this->message ( $player, "�ִ� �������� �� ������ " . $count . "�� �����߽��ϴ� !" );
		return true;
	}
	public function giveHome(Player $player, $target = null) {
		if ($target == null) {
			$this->alert ( $player, "/givehome <���������>" );
			return false;
		}
		$target = $this->getServer ()->getPlayerExact ( $target );
		if ($target == null) {
			$this->message ( $player, "����� �������� �����Դϴ� ! �� �絵 �Ұ��� !" );
			$this->message ( $player, "�ش� ȸ������ �α��� �ϸ� �ٽýõ��غ����� !" );
			return false;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� �絵 ��ɾ� ����� �����մϴ�." );
			return false;
		}
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
			$this->alert ( $player, "�� ������ ���� �ƴ� ��ȣ�����Դϴ�. �絵 �Ұ���." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, "������ ���� �ƴմϴ�. �絵 �Ұ���." );
			return false;
		} else {
			if ($area ["resident"] [0] == $target->getName ()) {
				$this->alert ( $player, "�ڱ��ڽſ��� ���� ������ �� �����ϴ� !" );
				return false;
			}
			$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $player->getName (), $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
					$target ] );
			$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $target->getName (), $area ["ID"] );
			if ($this->checkEconomyAPI ()) $this->economyAPI->addMoney ( $player, $this->config_Data ["economy-home-reward-price"] );
			$this->message ( $player, "�ش� ���� {$target}�Կ��� �絵ó�� �߽��ϴ� !" );
		}
		return true;
	}
	public function protectArea(Player $player) {
		if (! isset ( $this->make_Queue [$player->getName ()] )) {
			$this->message ( $player, "�������� ������ �����մϴ�." );
			$this->message ( $player, "���Ͻô� ũ�⸸ŭ �𼭸��� ���� ��ġ���ּ���." );
			$this->make_Queue [$player->getName ()] ["pos1"] = false;
			$this->make_Queue [$player->getName ()] ["pos2"] = false;
			return true;
		} else {
			if (! $this->make_Queue [$player->getName ()] ["pos1"]) {
				$this->message ( $player, "ù��° �κ��� ���������ʾҽ��ϴ�!" );
				$this->message ( $player, "�������������� �ߴ��Ϸ��� (/sa cancel) !" );
				return true;
			}
			if (! $this->make_Queue [$player->getName ()] ["pos2"]) {
				$this->message ( $player, "�ι�° �κ��� ���������ʾҽ��ϴ�!" );
				$this->message ( $player, "�������������� �ߴ��Ϸ��� (/sa cancel) !" );
				return true;
			}
			$pos = $this->areaPosCast ( $this->make_Queue [$player->getName ()] ["pos1"], $this->make_Queue [$player->getName ()] ["pos2"] );
			$checkOverapArea = $this->db [$player->getLevel ()->getFolderName ()]->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
			if ($checkOverapArea != false) {
				if (! isset ( $this->make_Queue [$player->getName ()] ["overrap"] )) {
					$this->message ( $player, "�ش翵���� �ߺ��Ǵ� ������ �����Ǿ����ϴ�! ( ID: " . $checkOverapArea ["ID"] . ")" );
					$this->message ( $player, "��ġ�� ������������ �����ϰ� �� ������ �����ұ��?" );
					$this->message ( $player, "( ��:/sa make �ƴϿ�: /sa cancel )" );
					$this->make_Queue [$player->getName ()] ["overrap"] = true;
					return true;
				} else {
					while ( 1 ) {
						$checkOverapArea = $this->db [$player->getLevel ()->getFolderName ()]->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
						if ($checkOverapArea == false) break;
						$this->db [$player->getLevel ()->getFolderName ()]->removeAreaById ( $checkOverapArea ["ID"] );
						$this->message ( $player, $checkOverapArea ["ID"] . "�� ������ �����߽��ϴ�." );
					}
				}
			}
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addArea ( $player->getName (), $pos [0], $pos [1], $pos [2], $pos [3] );
			unset ( $this->make_Queue [$player->getName ()] );
			if ($check == false) {
				$this->message ( $player, "ó���������� �ߺ������� �ֽ��ϴ�. <��������>" );
				return true;
			} else {
				$this->message ( $player, $check . "�� ������ �����߽��ϴ�." );
				$this->message ( $player, "/sa protect �� ��ȣ���θ� ��������" );
				return true;
			}
		}
	}
	public function homelist(Player $player) {
		// TODO ��¹��
		$this->message ( $player, "/home *����ȣ �� �ش� ������ ��������" );
		$this->message ( $player, "/buyhome ����ȣ �� �ش� �� ���Ű���" );
		// TODO /home *��ȣ
		// TODO /buyhome ��ȣ
		// TODO /home *��ȣ �۹̼�
	}
	public function whiteWorld(Player $player) {
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			$this->db [$player->getLevel ()->getFolderName ()]->setWhiteWorld ( true );
			$this->message ( $player, $player->getLevel ()->getFolderName () . " �ʿ� ȭ��Ʈ���� ������ Ȱ��ȭ �߽��ϴ�." );
		} else {
			$this->db [$player->getLevel ()->getFolderName ()]->setWhiteWorld ( false );
			$this->message ( $player, $player->getLevel ()->getFolderName () . " �ʿ� ȭ��Ʈ���� ������ ���� �߽��ϴ�." );
		}
		return true;
	}
	public function buyHome(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ������ ã�� �� �����ϴ�." );
			$this->alert ( $player, "���� �ȿ����� ������ ��� ����� ���� !" );
			return false;
		} else {
			if ($area ["resident"] == null) {
				if ($this->checkEconomyAPI ()) {
					$money = $this->economyAPI->myMoney ( $player );
					if ($money < 5000) {
						$this->message ( $player, "���� �����ϴµ� �����߽��ϴ� !" );
						$this->message ( $player, "( �� ���Ű��� " . ($this->config_Data ["economy-home-price"] - $money) . "$ �� �� �ʿ��մϴ� !" );
						return false;
					}
				}
				$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
						$player->getName () ] );
				$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $player->getName (), $area ["ID"] );
				$this->message ( $player, "���������� ���� �����߽��ϴ� !" );
				if ($this->checkEconomyAPI ()) {
					$this->economyAPI->reduceMoney ( $player, $this->config_Data ["economy-home-price"] );
					$this->message ( $player, "( �� ���Ű��� " . $this->config_Data ["economy-home-price"] . "$ �� ���� �Ǿ����ϴ� !" );
				}
			} else {
				$this->alert ( $player, "�ش� ���� �̹� �����ڰ� �ֽ��ϴ�. ���źҰ� !" );
				return false;
			}
		}
		return true;
	}
	public function allowBlock(Player $player, $block) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ������ ã�� �� �����ϴ�." );
			$this->alert ( $player, "���� �ȿ����� ������� �� ������ ���� !" );
			return false;
		} else {
			if ($block == "clear") {
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "������� �� ������ �ʱ�ȭ�߽��ϴ� !" );
				return true;
			}
			if (isset ( explode ( ":", $block )[1] )) {
				if (! is_numeric ( explode ( ":", $block )[0] )) {
					$this->alert ( $player, "�� ���̵� ���� ���ڸ� �����մϴ� !" );
					return;
				}
				if (! is_numeric ( explode ( ":", $block )[1] )) {
					$this->alert ( $player, "�� ������ ���� ���ڸ� �����մϴ� !" );
					return;
				}
			} else {
				$block = $block . ":0";
			}
			
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addOption ( $area ["ID"], $block );
			if ($check) {
				$this->message ( $player, "������� �� ������ �߰��߽��ϴ� !" );
				$this->message ( $player, "( /sa allow clear ��ɾ�� ���� �ʱ�ȭ�� �����մϴ� !" );
			} else {
				$this->message ( $player, "�ش� ���� �̹� ������� �Ǿ��ֽ��ϴ� !" );
				$this->message ( $player, "( /sa allow clear ��ɾ�� ���� �ʱ�ȭ�� �����մϴ� !" );
			}
		}
	}
	public function forbidBlock(Player $player, $block) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ������ ã�� �� �����ϴ�." );
			$this->alert ( $player, "���� �ȿ����� �������� �� ������ ���� !" );
			return false;
		} else {
			if ($block == "clear") {
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "�������� �� ������ �ʱ�ȭ�߽��ϴ� !" );
				return true;
			}
			if (isset ( explode ( ":", $block )[1] )) {
				if (! is_numeric ( explode ( ":", $block )[0] )) {
					$this->alert ( $player, "�� ���̵� ���� ���ڸ� �����մϴ� !" );
					return;
				}
				if (! is_numeric ( explode ( ":", $block )[1] )) {
					$this->alert ( $player, "�� ������ ���� ���ڸ� �����մϴ� !" );
					return;
				}
			} else {
				$block = $block . ":0";
			}
			
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addOption ( $area ["ID"], $block );
			if ($check) {
				$this->message ( $player, "�������� �� ������ �߰��߽��ϴ� !" );
				$this->message ( $player, "( /sa forbid clear ��ɾ�� ���� �ʱ�ȭ�� �����մϴ� !" );
			} else {
				$this->message ( $player, "�ش� ���� �̹� �������� �Ǿ��ֽ��ϴ� !" );
				$this->message ( $player, "( /sa forbid clear ��ɾ�� ���� �ʱ�ȭ�� �����մϴ� !" );
			}
		}
	}
	public function protect(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ������ ã�� �� �����ϴ�." );
			$this->alert ( $player, "���� �ȿ����� �������� ������� ������ ���� !" );
			return false;
		} else {
			if ($this->db [$player->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] )) {
				$this->db [$player->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], false );
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "�������� ��� ������ �Ϸ�Ǿ����ϴ� !" );
				$this->message ( $player, "( / sa forbid �����̵� - ������ ������ ������ ���� )" );
			} else {
				$this->db [$player->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], true );
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "�������� ����� ������ �Ϸ�Ǿ����ϴ� !" );
				$this->message ( $player, "( / sa allow �����̵� - ������ ����� ������ ���� )" );
			}
		}
	}
	public function pvp(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ������ ã�� �� �����ϴ�." );
			$this->alert ( $player, "���� �ȿ����� PVP ���/����� �����̰����մϴ�." );
			return false;
		} else {
			if ($this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) {
				$this->db [$player->getLevel ()->getFolderName ()]->setPvpAllow ( $area ["ID"], false );
				$this->message ( $player, "PVP ����� ������ �Ϸ�Ǿ����ϴ�!" );
				$this->message ( $player, "( /sa pvp �ٽ� �Է½� ��뼳�� ���� )" );
			} else {
				$this->db [$player->getLevel ()->getFolderName ()]->setPvpAllow ( $area ["ID"], true );
				$this->message ( $player, "PVP ��� ������ �Ϸ�Ǿ����ϴ�!" );
				$this->message ( $player, "( /sa pvp �ٽ� �Է½� ����뼳�� ���� )" );
			}
		}
	}
	public function welcome(Player $player, $text) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� ȯ���޽��� �����̰����մϴ�." );
			return false;
		} else {
			if ($area ["resident"] [0] != $player->getName () and ! $player->isOp ()) {
				$this->alert ( $player, "������ ���� �ƴմϴ�. ȯ���޽��� ���� �Ұ���." );
				return false;
			}
			$this->db [$player->getLevel ()->getFolderName ()]->setWelcome ( $area ["ID"], $text );
			$this->message ( $player, "ȯ���޽��� ������ �Ϸ�Ǿ����ϴ�!" );
		}
	}
	public function sellHome(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� �Ǹ� ��ɾ� ����� �����մϴ�." );
			return false;
		}
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
			$this->alert ( $player, "�� ������ ���� �ƴ� ��ȣ�����Դϴ�. �Ǹ� �Ұ���." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName () and ! $player->isOp ()) {
			$this->alert ( $player, "������ ���� �ƴմϴ�. �Ǹ� �Ұ���." );
			return false;
		} else {
			$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $player->getName (), $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ ] );
			$this->message ( $player, "�ش� ���� �Ǹ�ó�� �߽��ϴ� !" );
			if ($this->checkEconomyAPI ()) {
				$this->economyAPI->addMoney ( $player, $this->config_Data ["economy-home-reward-price"] );
				$this->message ( $player, "����ݾ� : " . $this->config_Data ["economy-home-reward-price"] . "$ �� ���޵Ǿ����ϴ� !" );
			}
		}
	}
	public function goHome(Player $player, $home_number) {
		if (! is_numeric ( $home_number )) {
			$this->alert ( $player, "����ȣ�� ���ڸ� �����մϴ� !" );
			return false;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getUserHome ( $player->getName (), $home_number );
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getAreaById ( $area );
		if ($area == false) {
			$this->alert ( $player, "�ش� ��ȣ�� ���� ���������ʽ��ϴ�" );
			return false;
		}
		$x = (($area ["startX"]) + 1);
		$z = (($area ["startZ"]) + 1);
		$y = ($player->getLevel ()->getHighestBlockAt ( $x, $z ) + 2);
		$player->teleport ( new Vector3 ( $x, $y, $z ) );
		return true;
	}
	public function printHomeList(Player $player) {
		$homes = $this->db [$player->getLevel ()->getFolderName ()]->getUserHomes ( $player->getName () );
		if ($homes == false) {
			$this->alert ( $player, "������ �����ϰ� ���� �ʽ��ϴ� !" );
			return false;
		}
		$this->message ( $player, "���� ���� ������Ʈ�� ����մϴ�. (����ȣ�� ����)" );
		foreach ( $homes as $index => $home ) {
			$this->message ( $player, $index . "�� " );
		}
		return true;
	}
	public function helpPage(Player $player, $pageNumber = 1) {
		$this->message ( $player, "* ���ÿ��� ������ ����մϴ� (" . $pageNumber . "/2) *" );
		if ($pageNumber == 1) {
			$this->message ( $player, "/sa whiteworld - ȭ��Ʈ���� ����", "" );
			$this->message ( $player, "/sa make - ���� ������ȣ ����", "" );
			$this->message ( $player, "/sa delete - ������ȣ-Ȩ ����", "" );
			$this->message ( $player, "/sa protect - ���� ������ȣ���� ����", "" );
			$this->message ( $player, "/sa allow - ���� ����ų �� ����", "" );
			$this->message ( $player, "/sa forbid - ���� ������ų �� ����", "" );
			$this->message ( $player, "( /sa help 1|2 - ������ ����մϴ� ) " );
		} else {
			$this->message ( $player, "/sa homelimit - ���������Ѱ� ����", "" );
			$this->message ( $player, "/sa economy - ���ڳ�� Ȱ��ȭ ����", "" );
			$this->message ( $player, "/sa homeprice - ������ ����", "" );
			$this->message ( $player, "/sa hometax - ������ ����", "" );
			$this->message ( $player, "/sa fence - �ڵ���Ÿ������ ����", "" );
			$this->message ( $player, "/sa message - �����޽���ǥ�� ����", "" );
			$this->message ( $player, "( /sa help 1|2 - ������ ����մϴ� ) " );
		}
	}
	public function invite(Player $player, $invited) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� ���� ��ɾ� ����̰����մϴ�." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, "������ ���� �ƴմϴ�. �ʴ� �Ұ���." );
			return false;
		} else {
			if ($area ["resident"] [0] == $invited) {
				$this->alert ( $player, "�ڱ��ڽſ��� ���� ������ �� �����ϴ� !" );
				return false;
			}
			foreach ( $area ["resident"] as $resident ) {
				if ($invited == $resident) {
					$this->alert ( $player, $resident . "���� �̹� ������ ���� �޾ҽ��ϴ� !" );
					$this->message ( $player, "( /inviteclear �� ��� ���� �������� )" );
					return false;
				}
			}
			$invite = $this->getServer ()->getPlayerExact ( $invited );
			
			if ($invite != null) {
				$this->db [$player->getLevel ()->getFolderName ()]->addResident ( $area ["ID"], $invite->getName () );
				$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $invite->getName (), $area ["ID"] );
				
				$this->message ( $player, "�� ���� " . $invited . "�԰� �����߽��ϴ�." );
				$this->message ( $player, "( /inviteclear �� ��� ���� �������� )" );
				$this->message ( $player, "( /invitelist �� �� ���� ���� ���� Ȯ�ΰ��� )" );
				
				$this->message ( $invite, $area ["ID"] . "�� ���� " . $player->getName () . "���� �����߽��ϴ� !" );
				$this->message ( $invite, "( /inviteout ���� ���� �ʴ븦 ������ �� �ֽ��ϴ� ! )" );
				$this->message ( $invite, "( /invitelist �� �� ���� ���� ���� Ȯ�ΰ��� )" );
			} else {
				$this->alert ( $player, "�ش� ������ �������� �Դϴ� ! ( �ʴ�Ұ��� )" );
			}
		}
		return true;
	}
	public function inviteout(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� �ʴ����� ��ɾ� ����̰����մϴ�." );
			return false;
		}
		if ($area ["resident"] [0] == $player->getName ()) {
			$this->alert ( $player, "������ ���Դϴ�, �ʴ����� �Ұ��� !" );
			return false;
		} else {
			foreach ( $area ["resident"] as $index => $resident ) {
				if ($player->getName () == $resident) {
					$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $resident, $area ["ID"] );
					$this->db [$player->getLevel ()->getFolderName ()]->removeResident ( $area ["ID"], $resident );
					$this->message ( $player, "���������� �ʴ븦 �����߽��ϴ� !" );
					
					$owner = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
					if ($owner != null) $this->message ( $owner, "{$area ["ID"]}�� ������ {$player->getName()}���� ������ �����߽��ϴ�" );
					return true;
				}
			}
			$this->alert ( $player, "�� ���� �ʴ���� �̷��� �����ϴ� ! ( �ʴ����� �Ұ��� ! )" );
			return false;
		}
	}
	public function invitelist(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� �ʴ븮��Ʈ ��ɾ� ����̰����մϴ�." );
			return false;
		} else {
			$residents = null;
			foreach ( $area ["resident"] as $index => $resident )
				$residents .= "[{$index}]" . $resident . " ";
			$this->message ( $player, "�� ���� ���� �ް� �ִ� ������ ����մϴ� !\n{$residents}" );
			return true;
		}
	}
	public function inviteclear(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "���� ��ġ���� ���� ã�� �� �����ϴ�." );
			$this->alert ( $player, "�� �ȿ����� �������� ��ɾ� ����̰����մϴ�." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, "������ ���� �ƴմϴ�. �ʴ���� �Ұ���." );
			return false;
		} else {
			foreach ( $area ["resident"] as $res )
				if ($res != $player->getName ()) $this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $res, $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
					$player->getName () ] );
			$this->message ( $player, "�� ���� ��������� ��� �����߽��ϴ�." );
		}
		
		return true;
	}
	public function areaPosCast(Position $pos1, Position $pos2) {
		$startX = ( int ) $pos1->getX ();
		$startZ = ( int ) $pos1->getZ ();
		$endX = ( int ) $pos2->getX ();
		$endZ = ( int ) $pos2->getZ ();
		if ($startX > $endX) {
			$backup = $startX;
			$startX = $endX;
			$endX = $backup;
		}
		if ($startZ > $endZ) {
			$backup = $startZ;
			$startZ = $endZ;
			$endZ = $backup;
		}
		return [ 
				$startX,
				$endX,
				$startZ,
				$endZ ];
	}
	public function rent(Player $player, $price = null) {
		if ($this->checkEconomyEnable () and $this->checkEconomyAPI ()) {
			$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
			if ($area == false) {
				$this->alert ( $player, "������ ã�� �� �����ϴ�." );
				return false;
			}
			if ($area ["resident"] [0] == $player->getName ()) {
				if (isset ( $this->rent_Queue [$player->getName ()] )) {
					$money = $this->economyAPI->myMoney ( $this->rent_Queue [$player->getName ()] ["buyer"] );
					if ($money < $price) {
						$this->alert ( $this->rent_Queue [$player->getName ()] ["buyer"], "�����Ϸ��� �Ӵ�� �����մϴ� ! �Ӵ��û ���� !" );
						$this->alert ( $player, "�Ӵ� ��û���� ���� ���������� �Ӵ��û�� ��ҵǾ����ϴ�" );
						unset ( $this->rent_Queue [$player->getName ()] );
						return false;
					}
					
					$id = &$this->rent_Queue [$player->getName ()] ["ID"];
					$buyer = &$this->rent_Queue [$player->getName ()] ["buyer"];
					$price = &$this->rent_Queue [$player->getName ()] ["price"];
					
					$this->economyAPI->reduceMoney ( $this->rent_Queue [$player->getName ()] ["buyer"], $price );
					$this->economyAPI->addMoney ( $player, $price );
					
					$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $buyer->getName (), $id );
					$this->db [$player->getLevel ()->getFolderName ()]->addResident ( $id, $buyer->getName () );
					$this->message ( $player, "{$id}�� ������ ���������� �Ӵ� �߽��ϴ� !" );
					$this->message ( $buyer, "{$id}�� ������ ���������� �Ӵ� �޾ҽ��ϴ� !" );
					
					unset ( $this->rent_Queue [$player->getName ()] );
					return true;
				}
				if ($area ["rent-allow"] == true) {
					$this->db [$player->getLevel ()->getFolderName ()]->setRentAllow ( $area ["ID"], false );
					$this->message ( $player, "�� ���� ���� �Ӵ��û�� ���� �ʰ� ó���߽��ϴ� !" );
					$this->message ( $player, "( /rent �� �ٽ��ѹ� ���� Ȱ��ȭ���� ! )" );
				} else {
					$this->db [$player->getLevel ()->getFolderName ()]->setRentAllow ( $area ["ID"], true );
					$this->message ( $player, "�� ���� ���� �Ӵ��û�� �ްԲ� ó���߽��ϴ� !" );
					$this->message ( $player, "( /rent �� �ٽ��ѹ� ���� ��Ȱ��ȭ���� ! )" );
				}
				return false;
			}
			foreach ( $area ["resident"] as $resident ) {
				if ($resident == $player->getName ()) {
					$this->alert ( $player, "�̹� �ش� ������ �����ް� �ֽ��ϴ� ! ��Ʈ�Ұ��� !" );
					return false;
				}
			}
			if ($price == null) {
				$this->message ( $player, "/rent <������ ����> - �ѹ��� ����" );
				$this->message ( $player, "��û �� �������� �³�/������ �ϰԵǸ�" );
				$this->message ( $player, "10�ʾȿ� �³��� �� ���Ű� �Ϸ� �˴ϴ�." );
				return false;
			} else {
				if (! is_numeric ( $price )) {
					$this->alert ( $player, "�Ӵ��� ���ڷθ� �Է� �����մϴ� !" );
					return false;
				} else {
					if ($area ["rent-allow"] == false) {
						$this->message ( $player, "�� ���� �����ڰ� �ش� ���� �Ӵ��û�� �����ʽ��ϴ� !" );
						$this->message ( $player, $area ["resident"] [0] . "�Կ��� �Ӵ������ ��û�غ����� !" );
						return false;
					}
					$money = $this->economyAPI->myMoney ( $player );
					if ($money < $price) {
						$this->alert ( $player, "�����Ϸ��� �Ӵ�� �����մϴ� ! �Ӵ��û ���� !" );
						return false;
					}
					$owner = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
					if ($owner == null) {
						$this->message ( $player, "�������� �������� �����Դϴ� ! ���źҰ��� !" );
						$this->message ( $player, $area ["resident"] [0] . "���� �α��� �ϸ� �ٽýõ��غ����� !" );
						return false;
					}
					if (isset ( $this->rent_Queue [$owner->getName ()] )) {
						$this->alert ( $player, "�̹� �������� �ٸ� �Ӵ��û�� �ް� �ֽ��ϴ� !" );
						$this->alert ( $player, "10�� �Ŀ� �ٽ� �Ӵ��û �õ� ���ּ���!" );
						return false;
					}
					$this->message ( $owner, $player->getName () . "���� " . $area ["ID"] . "�� ���� �Ӵ�ޱ� ���մϴ� !" );
					$this->message ( $owner, "�Ӵ��� " . $price . "$ �� ���� �����̸�, ���� ���޵˴ϴ� !" );
					$this->message ( $owner, "( 10�� ������ /rent ��ɾ ���ø� ���ó���˴ϴ�. )" );
					$this->rent_Queue [$owner->getName ()] ["ID"] = $area ["ID"];
					$this->rent_Queue [$owner->getName ()] ["buyer"] = $player;
					$this->rent_Queue [$owner->getName ()] ["price"] = $price;
					$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
							$this,
							"rentTimeout" ], [ 
							$owner,
							$player ] ), 200 );
					$this->message ( $player, "�����ο��� �ش� ��û�� ���½��ϴ� !" );
					return true;
				}
			}
		} else {
			$this->alert ( $player, "���ڳ�̰� ��Ȱ��ȭ �Ǿ��ֽ��ϴ� ( ���Ұ� )" );
		}
	}
	public function rentTimeout(Player $owner, CommandSender $buyer) {
		if (isset ( $this->rent_Queue [$owner->getName ()] )) {
			$this->alert ( $this->rent_Queue [$owner->getName ()] ["buyer"], "�������� �ǸŸ� �������ʽ��ϴ� ! ( 10�� Ÿ�Ӿƿ� )" );
			$this->alert ( $owner, "�Ӵ��û�� �ڵ����� �����߽��ϴ� ! (10�� Ÿ�Ӿƿ�)" );
			unset ( $this->rent_Queue [$owner->getName ()] );
		}
	}
	public function deleteHome(Player $player) {
		if (isset ( $this->delete_Queue [$player->getName ()] )) {
			$this->db [$player->getLevel ()->getFolderName ()]->removeAreaById ( $this->delete_Queue [$player->getName ()] ["ID"] );
			$this->message ( $player, "���� ������ �Ϸ��߽��ϴ�!" );
			unset ( $this->delete_Queue [$player->getName ()] );
			return true;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == false) {
			$this->alert ( $player, "������ ã�� �� �����ϴ�." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			if (! $player->isOp ()) {
				$this->alert ( $player, "���� �����ְ� �ƴմϴ�, �����Ұ���." );
				return false;
			} else {
				$this->delete_Queue [$player->getName ()] = $area;
				if ($area ["resident"] [0] != null) $this->message ( $player, $area ["resident"] [0] . "�� ������ �����Ͻðڽ��ϱ�?." );
				if ($area ["resident"] [0] == null) $this->message ( $player, "�����ְ� ���� ������ �����Ͻðڽ��ϱ�?." );
				$this->message ( $player, "������� �ٽ��ѹ� ��ɾ," );
				$this->message ( $player, "�ƴ� ��� /sa cancel�� ���ּ���." );
			}
		} else {
			$this->delete_Queue [$player->getName ()] = $area;
			$this->message ( $player, "���� ������ �����Ͻðڽ��ϱ� ?" );
			$this->message ( $player, "������� �ٽ��ѹ� ��ɾ," );
			$this->message ( $player, "�ƴ� ��� /sa cancel�� ���ּ���." );
		}
		return true;
	}
	public function SimpleArea(Player $player) {
		$size = ( int ) round ( $this->getHomeSize () / 2 );
		$startX = ( int ) round ( $player->x - $size );
		$endX = ( int ) round ( $player->x + $size );
		$startZ = ( int ) round ( $player->z - $size );
		$endZ = ( int ) round ( $player->z + $size );
		
		if ($this->checkEconomyAPI ()) {
			$money = $this->economyAPI->myMoney ( $player );
			if ($money < 5000) {
				$this->message ( $player, "���� �����ϴµ� �����߽��ϴ� !" );
				$this->message ( $player, "( �� ���Ű��� " . ($this->config_Data ["economy-home-price"] - $money) . "$ �� �� �ʿ��մϴ� !" );
				return false;
			}
		}
		
		$area_id = $this->db [$player->level->getFolderName ()]->addArea ( $player->getName (), $startX, $endX, $startZ, $endZ, true );
		
		if ($area_id == false) {
			$this->message ( $player, "�ٸ� ������ ������ ��Ĩ�ϴ�, �����Ұ� !" );
		} else {
			foreach ( $this->config_Data ["default-protect-blocks"] as $protect_block )
				$this->db [$player->level->getFolderName ()]->addOption ( $area_id, $protect_block );
			$this->message ( $player, "���������� ���� �����߽��ϴ� !" );
			if ($this->checkEconomyAPI ()) {
				$this->economyAPI->reduceMoney ( $player, $this->config_Data ["economy-home-price"] );
				$this->message ( $player, "�� ���Ű��� " . $this->config_Data ["economy-home-price"] . "$ �� ���� �Ǿ����ϴ� !" );
			}
		}
	}
	public function autoAreaSet(Player $player) {
		$size = ( int ) round ( $this->getHomeSize () / 2 );
		$startX = ( int ) round ( $player->x - $size );
		$endX = ( int ) round ( $player->x + $size );
		$startZ = ( int ) round ( $player->z - $size );
		$endZ = ( int ) round ( $player->z + $size );
		
		$area_id = $this->db [$player->level->getFolderName ()]->addArea ( null, $startX, $endX, $startZ, $endZ, true );
		
		if ($area_id == false) {
			$this->message ( $player, "�ٸ� ������ ������ ��Ĩ�ϴ�, �����Ұ� !" );
		} else {
			foreach ( $this->config_Data ["default-protect-blocks"] as $protect_block )
				$this->db [$player->level->getFolderName ()]->addOption ( $area_id, $protect_block );
			$this->message ( $player, "���������� ���� �����߽��ϴ� !" );
		}
	}
	public function getHomeSize() {
		return $this->config_Data ["default-home-size"];
	}
	public function checkShowPreventMessage() {
		return ( bool ) $this->config_Data ["show-prevent-message"];
	}
	public function checkEconomyEnable() {
		return ( bool ) $this->config_Data ["economy-enable"];
	}
	public function checkEconomyAPI() {
		return (($this->getServer ()->getLoader ()->findClass ( 'onebone\\economyapi\\EconomyAPI' )) == null) ? false : true;
	}
	public function checkHomeLimit(Player $player) {
		if ($this->config_Data ["maximum-home-limit"] == 0 or $player->isOp ()) return true;
		if (! $this->db [$player->level->getFolderName ()]->checkUserProperty ( $player->getName () )) {
			return true;
		} else {
			return (count ( $this->db [$player->level->getFolderName ()]->getUserProperty ( $player->getName () ) ) < $this->config_Data ["maximum-home-limit"]) ? true : false;
		}
	}
	public function message(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>