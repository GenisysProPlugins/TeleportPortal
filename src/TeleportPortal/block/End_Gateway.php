<?php

namespace TeleportPortal\block;

use pocketmine\block\Solid;

class End_Gateway extends Solid{

 protected $id = 209;

 public function __construct(int $meta = 0){
 
  $this->meta = $meta;
 }

 public function getName() : string{
  
  return "End Gateway";
 }

}