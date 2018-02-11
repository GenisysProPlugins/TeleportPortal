<?php

namespace TeleportPortal\block;

use pocketmine\block\Solid;

class End_Portal extends Solid{

 protected $id = 119;

 public function __construct(int $meta = 0){
 
  $this->meta = $meta;
 }

 public function getName() : string{
  
  return "End Portal";
 }

}