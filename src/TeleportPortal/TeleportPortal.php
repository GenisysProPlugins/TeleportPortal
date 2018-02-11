<?php

namespace TeleportPortal;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\level\Level;

use pocketmine\command\{Command,CommandSender};

use pocketmine\item\Item;
use pocketmine\block\BlockFactory;
use pocketmine\math\Vector3;

use pocketmine\entity\Entity;
use pocketmine\level\Position;

use pocketmine\event\block\{BlockPlaceEvent,BlockBreakEvent};

use pocketmine\event\player\{PlayerMoveEvent,PlayerQuitEvent};

use pocketmine\level\format\generic\BaseLevelProvider;
use pocketmine\level\generator\Generator;
use pocketmine\level\sound\GenericSound;

use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\TransferPacket;

use TeleportPortal\block\End_Portal;
use TeleportPortal\block\End_Gateway;
use TeleportPortal\block\Shulker_Box;

class TeleportPortal extends PluginBase implements Listener{
 
 public $edit = [];
 public $logo = [];
 
 public function onLoad(){
  
  BlockFactory::registerBlock(new End_Portal());
  BlockFactory::registerBlock(new End_Gateway());
  BlockFactory::registerBlock(new Shulker_Box());
  
  for($meta = 0; $meta <= 15; $meta ++) Item::addCreativeItem(Item::get(218, $meta));
  

 }
 
 public function onEnable()
{
  
  
  //$this->LoadWorld();
  
  @mkdir($this->getDataFolder());
 
  $this->Config = new Config($this->getDataFolder()."TeleportPortal.yml", Config::YAML,array());
  $this->run = $this->Config->getAll();
  
  $this->fconfig = new Config($this->getDataFolder()."FloatingText.yml", Config::YAML, []);
  
  $this->ftext = $this->fconfig->getAll();
 
  $this->getServer()->getPluginManager()->registerEvents($this,$this);
  
  $this->getLogger()->info("加载成功.");
 }

 public function LoadWorld(){

  $level = $this->getServer()->getDefaultLevel();
  $path = $level->getFolderName();
  
  
  $p2 = $this->getServer()->getDataPath() . "worlds/";
  
  
  $dirnowfile = scandir($p2, 1);
 
  foreach ($dirnowfile as $dirfile){
 
   if($dirfile != '.' && $dirfile != '..' && $dirfile != $path && is_dir($p2.$dirfile)){
  
    if(!$this->getServer()->isLevelLoaded($dirfile)){
   
     $this->getLogger()->info("Loading world: {$dirfile}");
     $this->getServer()->generateLevel($dirfile);
     $this->getServer()->loadLevel($dirfile);
     $level = $this->getServer()->getLevelbyName($dirfile);
    
     if($level->getName() !== $dirfile){
    
      $level = $this->getServer()->getLevelByName($dirfile);
      $provider = $level->getProvider();
      $provider->getLevelData()->LevelName = new StringTag("LevelName", $dirfile);
      $provider->saveLevelData();
      
      $level->save(true);
     }
    }
   }
  }
 }
 
 public function save(){
 
  $this->Config->setAll($this->run);
  $this->Config->save();
 }
 
 public function onCommand(CommandSender $sender, Command $command, $label, array $args){

  if(!$sender instanceof Player) return $sender->sendMessage("§l§6Please run this command in-game.");
 
  $x = $sender->x;
  $y = $sender->y;
  $z = $sender->z;
  $level = $sender->getLevel()->getName();
  
  switch($command->getName()){
  
   case "go":
    
    if(!isset($args[0])){
     $sender->sendMessage("§l§eUsage§7: §6/go help");
     return true;
    }
    
    switch($args[0]){
    
     case "add":
    
      if(!isset($args[1])){
       $sender->sendMessage("§l§eUsage§7: §6/go add <PortalName>");
       return true;
      }
      
      if(isset($this->run[$args[1]])){
       $sender->sendMessage("§l§cPortal§e {$args[1]} §cAlready existed.");
       return true;
      }
      
      $this->run[$args[1]] = [
       "pos" => null,
       "level" => null,
       "blockpos" => []
      ];
    
      if(isset($args[3])){
      
       $ip = explode(".",$args[2]);
       
       if(!isset($ip[3]) ||
       !is_numeric($ip[0]) ||
       !is_numeric($ip[1]) ||
       !is_numeric($ip[2]) ||
       !is_numeric($ip[3]) ||
       !is_numeric($args[3])) return $sender->sendMessage("§l§eUsage§7: §6/go add <PortalName> <Address> <Port>");
        
       unset($this->run[$args[1]]["pos"],$this->run[$args[1]]["level"]);
        
       $this->run[$args[1]]["address"] = $args[2];
       $this->run[$args[1]]["port"] = $args[3];
        
       $sender->sendMessage("§l§6Successfully create a transfer portal,you can transfer to {$args[1]} , address: {$args[2]} ,port: {$args[3]}");

       return true;
       
      }else{
     
      $sender->sendMessage("§l§6Succefully create a teleport portal, Please use: §e/go pos <{$args[1]}> §6to set the target position of this portal.");
      }
      break;
      
     case "pos":
      
      if(!isset($args[1])) return $sender->sendMessage("§l§eUsage§7: §6/go pos <PortalName>");
      
      if(!isset($this->run[$args[1]])) return $sender->sendMessage("§l§cPortal §e {$args[1]} §cdoesn't exist.");
      
      if(isset($this->run[$args[1]]["port"])) return $sender->sendMessage("§l§cPortal§e {$args[1]} §cis a transfer portal.");
      
      $this->run[$args[1]]["pos"] = "{$x}:{$y}:{$z}";
      $this->run[$args[1]]["level"] = $level;
      
      $sender->sendMessage("§l§6Successfully create a teleport portal,可以传送到§e{$level}§6地图的 §e{$x}:{$y}:{$z} §6坐标!");
      break;
     
     case "startUp":
     
      if(!isset($args[1])) return $sender->sendMessage("§l§eUsage§7: §6/go del <PortalName>");
   
      if(!isset($this->run[$args[1]])) return $sender->sendMessage("§l§cPortal §e {$args[1]} §cdoesn't exist.");
     
      if(isset($this->edit[$sender->getName()])) return $sender->sendMessage("§l§cYou are already in edit mode, Please end your work before beginning a new work.");
     
      $this->edit[$sender->getName()] = $args[1];
      
      $sender->sendMessage("§l§bBegin to edit portal §6{$args[1]} §b, now using portal blocks in your container to build a portal you like!");
     
      $sender->setGamemode(1);
     
      $sender->getInventory()->addItem(new Item(90,0,64));
      $sender->getInventory()->addItem(new Item(119,0,64));
      $sender->getInventory()->addItem(new Item(209,0,64));
      break;
         
     case "endUp":
     
      if(!isset($this->edit[$sender->getName()])) return $sender->sendMessage("§l§cYou are not in edit mode.");
     
      $PosName = $this->edit[$sender->getName()];
     
      unset($this->edit[$sender->getName()]);
     
      $sender->sendMessage("§l§bSuccessfully stop editing §6{$PosName} §b.");
      break;
     
     case "del":
   
      if(!isset($args[1])) return $sender->sendMessage("§l§eUsage§7: §6/go del <PortalName>");
   
      if(isset($this->run[$args[1]])){
   
       unset($this->run[$args[1]]);
       $sender->sendMessage("§l§6Successfully delete portal§e {$args[1]} §6.");
     
      }else{
   
       $sender->sendMessage("§l§cPortal§e {$args[1]} §cdoesn't exist.");
      }
      break;
   
     case "help":
   
      foreach([
       "§l§6>>§r§a----------- §l§eTELEPORT_PORTA HELP §r§a-----------§l§6<<",
       " §l§a- §bCreate a new teleport portal §a- §e/go add <PortalName>",
       " §l§a- §bSet the target position of a teleport portal §a- §e/go pos <PortalName>",
       " §l§a- §bCreate a new transfer portal §a- §e/go add <PortalName> <address> <port>",
       " §l§a- §b编辑指定传送门 §a- §e/go startUp <PortalName>",
       " §l§a- §b结束建造传送门 §a- §e/go endUp",
       " §l§a- §b删除指定传送门 §a- §e/go del <PortalName>",
       "§l§6>>§r§a----------------------------------------------§l§6<<"
      ] as $help){
   
       $sender->sendMessage($help);
      }
      break;
      
     default:
      
      $sender->sendMessage("§l§eUsage§7: §6/go help");
      break;
    }
  
    $this->save();
    break;
   
   case "ftext":
   
    if(!isset($args[0]) || !isset($args[1]) and $args[0] !== "help"){
     $sender->sendMessage("§l§eUsage§7: §6/ftext help");
     return true;
    }
    
    switch($args[0]){
    
     case "add":
     
      if(isset($this->ftext[$args[1]])) return $sender->sendMessage("§l§cAlready exist a floatingtext named§7: §6{$args[1]}");
     
      if(!isset($args[2])) return $sender->sendMessage("§l§eUsage§7: §6/ftext add <name> <text>");
      
      $this->ftext[$args[1]] = [
       "ftpos" => "{$x}:{$y}:{$z}:{$level}",
       "fttext" => "{$args[2]}"
      ];
     
      $this->onBatch(new Position($x, $y, $z, $sender->level), $args[1], "{$args[2]}", true);
      
      $sender->sendMessage("§l§bSuccessfully add a floatingtext, name§7: §6{$args[1]}");
      break;
     
     case "del":
     
      if(!isset($this->ftext[$args[1]])) return $sender->sendMessage("§l§cname§7: §6{$args[1]} doesn't exist.");
      
      $element = explode(":", $this->ftext[$args[1]]["ftpos"]);
      
      $this->onBatch(new Position($element[0], $element[1], $element[2], $this->getServer()->getLevelByName($element[3])), $args[1]);
       
      unset($this->ftext[$args[1]]);
      $sender->sendMessage("§l§bSuccessfully delete§7: §6{$args[1]}");
      break;
     
     case "help":
     
      foreach([
       "§l§6>>§r§a------------------ §l§eFLOATING_TEXT HELP §r§a------------------§l§6<<",
       " §l§a- §bAdd a floatingtext §a- §e/ftext add <name> <text>",
       " §l§a- §bDelete target floatingtext §a- §e/ftext del <name>",
       "§l§6>>§r§a--------------- §l§eUse '.' to replace '\n' §r§a---------------§l§6<<"
      ] as $help){
     
       $sender->sendMessage($help);
      }
      break;
  
     default:
      
      $sender->sendMessage("§l§eUsage§7: §6/ftext help");
     break;
    }
    
    $this->fconfig->setAll($this->ftext);
    $this->fconfig->save();
    break;
    
   default:break;
  }
  
  unset($x, $y, $z, $level);
  return true;
 }
 
 public function onPlayerMove(PlayerMoveEvent $ev){
 
  $player = $ev->getPlayer();
  
  $pos = $player->getFloorX().":".$player->getFloorY().":".$player->getFloorZ().":".$player->getLevel()->getName();
  
  foreach($this->run as $PosName => $teleporter){
   
   foreach($teleporter["blockpos"] as $blockPos){
 
    $PortalPos = explode(":", $blockPos);
   
    if($player->distance(new Vector3($PortalPos[0] + 0.5, $PortalPos[1], $PortalPos[2] + 0.5)) <= 1.2 and $player->level->getName() === $PortalPos[3]){
   
     if(isset($teleporter["level"])){
      
      $World = $this->getServer()->getLevelByName($teleporter["level"]);
     
      if($World instanceof Level){
    
       $player->level->addSound(new GenericSound($player, 1030));
       $player->level->addSound(new GenericSound($player, 2000));
       
       $player->teleport($World->getSafeSpawn());
     
       if(isset($teleporter["pos"])){
     
        $pos = explode(":",$teleporter["pos"]);
        $player->teleport(new Vector3($pos[0],$pos[1],$pos[2]));
       }
       
       $player->sendMessage("§l§6Teleport to§e {$PosName} §6!");
      
      }else{
      
       $player->sendMessage("§l§cThis portal haven't set yet.");
      }  
    
     }elseif(isset($teleporter["address"]) and isset($teleporter["port"])){
      
      $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
          
      $address = strtolower($teleporter["address"]);
      $port = is_numeric($teleporter["port"])? (int)$teleporter["port"]: 19132;

    			$pk = new TransferPacket();
    			$pk->address = $address;
    			$pk->port = $port;
    			$player->dataPacket($pk);
    			
    			$this->getServer()->broadcastMessage("§l§6Player §6{$player->getName()} §5are transfering to §6{$PosName}");
     }
     
    }
   }   
  }  
  
  unset($ev, $player, $pos, $PosName, $teleporter);
 }
 
 public function onBlockPlace(BlockPlaceEvent $ev){
 
  $player = $ev->getPlayer();
  $block = $ev->getBlock();
  
  if(isset($this->edit[$player->getName()]) and in_array($block->getId(), [90, 119, 209])){
   
   $PosName = $this->edit[$player->getName()];
   
   $pos = $block->x.":".$block->y.":".$block->z.":".$block->getLevel()->getName();
   
   $this->run[$PosName]["blockpos"][] = $pos;
   
   $this->save();
   
   $count = count($this->run[$PosName]["blockpos"]);
   
   $player->sendMessage("§l§aPortal §6{$PosName} §a's No.§6{$count} §ablock was recorded.");
  }
  
  unset($ev, $player);
 }
 
 public function onBlockBreak(BlockBreakEvent $ev){
 
  $player = $ev->getPlayer();
  $block = $ev->getBlock();
  
  if(isset($this->edit[$player->getName()]) and in_array($block->getId(), [90, 119, 209])){
  
   $PosName = $this->edit[$player->getName()];
   
   $pos = $block->x.":".$block->y.":".$block->z.":".$block->getLevel()->getName();   
   
   if(in_array($pos, $this->run[$PosName]["blockpos"])){
   
    $key = array_search($pos, $this->run[$PosName]["blockpos"]);
    
    unset($this->run[$PosName]["blockpos"][$key]);
    
    $this->save();
    
    $player->sendMessage("§l§bDelete §6{$PosName} §b's No. §6{$key} §bblock.");
   }
  }
  
  unset($ev, $player);  
 }
 
 public function onPlayerQuit(PlayerQuitEvent $ev){
 
  $player = $ev->getPlayer();
  
  if(isset($this->edit[$player->getName()])) unset($this->edit[$player->getName()]);
  
  unset($ev, $player);
 }
 
/**
================= FloatingText(Copy paste) =================
*/
 
 public function onBatch(Position $pos, $name, $text = "♥", $value = false){
  
  foreach($pos->level->getPlayers() as $player){
  
   if($value){
    
    $this->spawnText($pos, $player, $name, $text);
    
   }else{
    
    $this->removeText($player, $name);
   }
  }
  
  unset($pos, $name, $text, $value, $player);
 }
 
 public function spawnText(Position $pos, Player $player, $name, $text = "♥"){
  
  $pk = new \pocketmine\network\mcpe\protocol\AddEntityPacket();
  $pk->entityRuntimeId = Entity::$entityCount ++;
  $pk->type = 37;
  $pk->position = $pos;
  
  $flags = 0;
  $flags ^= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
  $flags ^= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
  $flags ^= 1 << Entity::DATA_FLAG_IMMOBILE;
  
  $pk->metadata = [
   Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
   Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, str_replace(["."],["\n"],$text)],
   38 => [7, -1],
   39 => [3, 0]
  ];
  
  $player->dataPacket($pk);
  
  if(!isset($this->logo[$name])) $this->logo[$name] = $pk->eid;
  
  unset($pos, $player, $name, $text, $pk);
 }
 
 public function removeText(Player $player, $name){
  
  $pk = new \pocketmine\network\mcpe\protocol\RemoveEntityPacket();
  $pk->entityUniqueId = $this->logo[$name];
  
  $player->dataPacket($pk);
  
  unset($player, $eid, $pk);
 }
 
 public function onPlayerJoin(\pocketmine\event\player\PlayerJoinEvent $ev){
  
  $player = $ev->getPlayer();
  
  foreach($this->ftext as $name => $Array){
  
   $element = explode(":", $Array["ftpos"]);
   
   if($player->getLevel()->getName() === $element[3]) $this->spawnText(new Position($element[0], $element[1], $element[2], $player->getLevel()), $player, $name, "{$Array["fttext"]}");
  }
  
  unset($ev, $player, $name, $Array);
 }
 
 public function onLevelChange(\pocketmine\event\entity\EntityLevelChangeEvent $ev){
 
  $player = $ev->getEntity();
  
  if($player instanceof Player){
   
   foreach($this->ftext as $name => $Array){
   
    $element = explode(":", $Array["ftpos"]);
    
    if($ev->getOrigin()->getName() === $element[3]){
    
     $this->removeText($player, $name);
     
    }elseif($ev->getTarget()->getName() === $element[3]){
    
     $this->spawnText(new Position($element[0], $element[1], $element[2], $player->getLevel()), $player, $name, "{$Array["fttext"]}");
     
    }
   }
  }
  
  unset($ev, $player);
 }
 
}

class CallbackTask extends \pocketmine\scheduler\Task{

 protected $callable;
 protected $args;
 
 public function __construct(callable $callable, array $args = []){
 
  $this->callable = $callable;
  $this->args = $args;
  $this->args[] = $this;
 }
	
	 public function getCallable(){
	 
	  return $this->callable;
	 }
	 
	 public function onRun($currentTicks){
	 
	  \call_user_func_array($this->callable, $this->args);
	 }
}






















