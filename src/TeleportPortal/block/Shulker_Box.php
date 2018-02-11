<?php

namespace TeleportPortal\block;

use pocketmine\block\Transparent;

class Shulker_Box extends Transparent{

 protected $id = 218;

 public function __construct(int $meta = 0){
 
  $this->meta = $meta;
 }

 public function getName() : string{
  
  return "Shulker_Box";
 }

}