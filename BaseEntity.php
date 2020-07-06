<?php

namespace boss\entity;

use boss\entity\monster\Monster;
use CortexPE\entity\mob\Blaze;
use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\Player;
use revivalpmmp\pureentities\entity\FlyingEntity;

abstract class BaseEntity extends Creature{

    protected $stayTime = 0;
    protected $moveTime = 0;

    /** @var Vector3|Entity */
    protected $baseTarget = null;

    private $movement = true;
    private $friendly = false;
    private $wallcheck = true;

    public function __destruct(){}

    public abstract function updateMove($tickDiff);

    public function getSaveId(): string {
        $class = new \ReflectionClass(get_class($this));
        return $class->getShortName();
    }

    public function isMovement() : bool{
        return $this->movement;
    }

    public function isFriendly() : bool{
        return $this->friendly;
    }

    public function isKnockback() : bool{
        return $this->attackTime > 0;
    }

    public function isWallCheck() : bool{
        return $this->wallcheck;
    }

    public function setMovement(bool $value){
        $this->movement = $value;
    }

    public function setFriendly(bool $bool){
        $this->friendly = $bool;
    }

    public function setWallCheck(bool $value){
        $this->wallcheck = $value;
    }

    public function getSpeed() : float{
        return 1;
    }

    public function initEntity(): void {
        parent::initEntity();

        if($this->namedtag->hasTag("WallCheck")){

            $this->setMovement($this->namedtag->getByte("Movement"));
        }

        if($this->namedtag->hasTag("WallCheck")){
            $this->setWallCheck($this->namedtag["WallCheck"]);
        }
    }

    public function saveNBT(): void{
        parent::saveNBT();
        $this->namedtag->setByte("Movement", $this->isMovement());
        $this->namedtag->setByte("WallCheck", $this->isWallCheck());
    }

    public function spawnTo(Player $player): void{
        if(
            !isset($this->hasSpawned[$player->getLoaderId()])
            && isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])
        ){
            $pk = new AddActorPacket();
            $pk->entityRuntimeId = $this->getID();
            $pk->type = "minecraft:vindicator";
            $pk->position = $this->asVector3();
            $pk->yaw = $this->yaw;
            $pk->pitch = $this->pitch;
            $pk->metadata = [
                Entity::DATA_FLAG_NO_AI => [Entity::DATA_TYPE_BYTE, 1],
            ];
            $player->dataPacket($pk);

            $this->hasSpawned[$player->getLoaderId()] = $player;
        }
    }

    public function updateMovement($teleport = false): void{
        if(
            $this->lastX !== $this->x
            || $this->lastY !== $this->y
            || $this->lastZ !== $this->z
            || $this->lastYaw !== $this->yaw
            || $this->lastPitch !== $this->pitch
        ){
            $this->lastX = $this->x;
            $this->lastY = $this->y;
            $this->lastZ = $this->z;
            $this->lastYaw = $this->yaw;
            $this->lastPitch = $this->pitch;
        }
        $this->addEntityMovement($this->chunk->getX(), $this->chunk->getZ(), $this->id, $this->x, $this->y, $this->z, $this->yaw, $this->pitch);
    }
    public function addEntityMovement(int $chunkX, int $chunkZ, int $entityId, float $x, float $y, float $z, float $yaw, float $pitch, $headYaw = null){
        $pk = new MoveActorAbsolutePacket();
        $pk->entityRuntimeId = $entityId;
        $pk->position = $this->asVector3();
        $pk->xRot = $pitch;
        $pk->yRot = $yaw;
        $pk->zRot = $yaw;
        $this->level->addChunkPacket($chunkX, $chunkZ, $pk);
    }
    public function isInsideOfSolid(): bool{
        $block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($this->y + $this->height - 0.18), Math::floorFloat($this->z)));
        $bb = $block->getBoundingBox();
        return $bb !== null and $block->isSolid() and !$block->isTransparent() and $bb->intersectsWith($this->getBoundingBox());
    }

    public function attack(EntityDamageEvent $source) : void{
        if($this->isKnockback() > 0) {
            return;
        }
        parent::attack($source);

        if($source->isCancelled() || !($source instanceof EntityDamageByEntityEvent)){
            return;
        }

        $this->stayTime = 0;
        $this->moveTime = 0;

        $damager = $source->getDamager();
        $motion = (new Vector3($this->x - $damager->x, $this->y - $damager->y, $this->z - $damager->z))->normalize();
        $this->motion->x = $motion->x * 0.19;
        $this->motion->z = $motion->z * 0.19;
        if(($this instanceof FlyingEntity) && !($this instanceof Blaze)){
            $this->motion->y = $motion->y * 0.19;
        }else{
            $this->motion->y = 0.6;
        }
    }

    public function knockBack(Entity $attacker, $damage, $x, $z, $base = 0.4): void{

    }

    public function entityBaseTick($tickDiff = 1): bool{
        \pocketmine\timings\Timings::$timerEntityBaseTick->startTiming();

        $hasUpdate = Entity::entityBaseTick($tickDiff);

        if($this->isInsideOfSolid()){
            $hasUpdate = true;

            $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
            $this->attack($ev);
        }

        if($this->moveTime > 0){
            $this->moveTime -= $tickDiff;
        }

        if($this->attackTime > 0){
            $this->attackTime -= $tickDiff;
        }

        \pocketmine\timings\Timings::$timerEntityBaseTick->startTiming();
        return $hasUpdate;
    }

    public function move($dx, $dy, $dz) : void{
        \pocketmine\timings\Timings::$entityMoveTimer->startTiming();

        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;

        $list = $this->level->getCollisionCubes($this, $this->level->getTickRate() > 1 ? $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz) : $this->boundingBox->addCoord($dx, $dy, $dz));
        if($this->isWallCheck()){
            foreach($list as $bb){
                $dx = $bb->calculateXOffset($this->boundingBox, $dx);
            }
            $this->boundingBox->offset($dx, 0, 0);

            foreach($list as $bb){
                $dz = $bb->calculateZOffset($this->boundingBox, $dz);
            }
            $this->boundingBox->offset(0, 0, $dz);
        }
        foreach($list as $bb){
            $dy = $bb->calculateYOffset($this->boundingBox, $dy);
        }
        $this->boundingBox->offset(0, $dy, 0);

        $this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);
        $this->checkChunks();

        $this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
        $this->updateFallState($dy, $this->onGround);

        \pocketmine\timings\Timings::$entityMoveTimer->stopTiming();
        return;
    }

    public function targetOption(Creature $creature, float $distance) : bool{
        return $this instanceof Monster && (!($creature instanceof Player) || ($creature->isSurvival() && $creature->spawned)) && $creature->isAlive() && !$creature->closed && $distance <= 81;
    }

}