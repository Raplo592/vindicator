<?php

namespace boss\entity\monster;

use boss\entity\monster\WalkingMonster;
use pocketmine\entity\Entity;
use pocketmine\item\DiamondSword;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\IntTag;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\entity\Creature;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\Player;

class Vindicator extends WalkingMonster{
    const NETWORK_ID = self::VINDICATOR;

    private $angry = 0;

    public $width = 0.72;
    public $height = 1.8;
    public $eyeHeight = 1.62;
    public $fireProof = false;
    public function getSpeed() : float{
        return 1.6;
    }

    public function initEntity(): void{
        parent::initEntity();
        if($this->namedtag->hasTag("Angry")) {
            $this->angry = (int)$this->namedtag->getInt("Angry");
        }

        $this->fireProof = true;
        $this->setDamage([0, 5, 9, 13]);
    }

    public function saveNBT(): void{
        parent::saveNBT();
        $this->namedtag->setInt("Angry", $this->angry);
    }

    public function getName(): string {
        return "PigZombie";
    }

    public function isAngry() : bool{
        return $this->angry > 0;
    }

    public function setAngry(int $val){
        $this->angry = $val;
    }

    public function targetOption(Creature $creature, float $distance) : bool{
        return $this->isAngry() && parent::targetOption($creature, $distance);
    }

    public function attack(EntityDamageEvent $source): void{
        parent::attack($source);

        if(!$source->isCancelled()){
            $this->setAngry(1000);
        }
    }
    public function spawnTo(Player $player): void{
        parent::spawnTo($player);

        $pk = new MobEquipmentPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->inventorySlot = $pk->hotbarSlot = ContainerIds::INVENTORY;
        $pk->item = ItemFactory::get(ItemIds::DIAMOND_SWORD);
        $player->dataPacket($pk);
    }

    public function attackEntity(Entity $player){
        if($this->attackDelay > 10 && $this->distanceSquared($player) < 2){
            $this->attackDelay = 0;

            $ev = new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage());
            $player->attack($ev);
        }
    }

    public function getDrops(): array {
        return [];
    }
}
