<?php

namespace boss\ui;

use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\inventory\BaseInventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\{CompoundTag, StringTag};
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\BlockEntityDataPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;
use pocketmine\tile\Tile;

class Window extends BaseInventory {

    const SEND_BLOCKS_FAKE = 0;
    const SEND_BLOCKS_REAL = 1;

    const FAKE_BLOCK_ID = BlockIds::CHEST;
    const FAKE_TILE_ID = Tile::CHEST;
    const FAKE_BLOCK_DATA = 0;

    const INVENTORY_HEIGHT = 3;
    /** @var BigEndianNBTStream|null */
    protected static $nbtWriter;
    /** @var Vector3[] */
    protected $holders = [];

    private $custom = "";

    public function __construct($name = ""){
        $this->custom = $name;
        parent::__construct();
    }

    public function onOpen(Player $player): void{
        if(!isset($this->holders[$id = $player->getId()])){
            parent::onOpen($player);

            $this->holders[$id] = $player->round()->add(0, self::INVENTORY_HEIGHT);
            $this->sendBlocks($player, self::SEND_BLOCKS_FAKE);

            $this->sendFakeTile($player);
            $this->sendInventoryInterface($player);
            $this->sendContents($player);
        }
    }

    protected function sendBlocks(Player $player, int $type): void{
        switch($type){
            case self::SEND_BLOCKS_FAKE:
                $player->getLevel()->sendBlocks([$player], $this->getFakeBlocks($this->holders[$player->getId()]));

                return;
            case self::SEND_BLOCKS_REAL:
                $player->getLevel()->sendBlocks([$player], $this->getRealBlocks($player, $this->holders[$player->getId()]));

                return;
        }

        throw new \Error("Unhandled type $type provided.");
    }

    protected function getFakeBlocks(Vector3 $holder): array{
        return [
            Block::get(static::FAKE_BLOCK_ID, static::FAKE_BLOCK_DATA)->setComponents($holder->x, $holder->y, $holder->z),
        ];
    }

    protected function getRealBlocks(Player $player, Vector3 $holder): array{
        return [
            $player->getLevel()->getBlockAt($holder->x, $holder->y, $holder->z),
        ];
    }

    protected function sendFakeTile(Player $player): void{
        $holder = $this->holders[$player->getId()];

        $pk = new BlockActorDataPacket();
        $pk->x = $holder->x;
        $pk->y = $holder->y;
        $pk->z = $holder->z;

        $writer = self::$nbtWriter ?? (self::$nbtWriter = new NetworkLittleEndianNBTStream());
        $tag = new CompoundTag("", [
            new StringTag("id", static::FAKE_TILE_ID),
        ]);
        $tag->setString("CustomName", $this->custom);


        $pk->namedtag = $writer->write($tag);
        $player->dataPacket($pk);
    }

    public function sendInventoryInterface(Player $player): void{
        $holder = $this->holders[$player->getId()];

        $pk = new ContainerOpenPacket();
        $pk->windowId = $player->getWindowId($this);
        $pk->type = $this->getNetworkType();
        $pk->x = $holder->x;
        $pk->y = $holder->y;
        $pk->z = $holder->z;
        $player->dataPacket($pk);

        $this->sendContents($player);
    }

    public function getNetworkType(): int{
        return WindowTypes::CONTAINER;
    }

    public function onClose(Player $player): void{
        if(isset($this->holders[$id = $player->getId()])){
            parent::onClose($player);

            $this->sendBlocks($player, self::SEND_BLOCKS_REAL);

            unset($this->holders[$id]);

            $pk = new ContainerClosePacket();
            $pk->windowId = $player->getWindowId($this);
            $player->dataPacket($pk);
        }
    }

    public function getName(): string{
        return "ChestInventory";
    }

    public function getDefaultSize(): int{
        return 27;
    }
}