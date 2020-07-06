<?php

namespace boss;

use boss\entity\monster\Vindicator;
use pocketmine\block\Air;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemIds;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\Player;
use pocketmine\item\enchantment\Enchantment;
use boss\ui\Window;

class Main extends PluginBase implements Listener
{

    private $idw = [];
    private $ids = [
        "276:0:1" => "1:50",
        "310:0:1" => "1:40",
        "311:0:1" => "1:40",
        "312:0:1" => "1:40",
        "313:0:1" => "1:40"
    ];
    /** @var Config */
    public $drops;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        Entity::registerEntity(Vindicator::class, true);
        @mkdir($folder = $this->getDataFolder());
        $this->drops = new Config($folder . "Drops.yml", Config::YAML, []);
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $commandLabel, array $args): bool
    {
        if (strtolower($cmd->getName()) == "boss") {
            if (!$sender instanceof Player) {
                $sender->sendMessage("Entre no jogo safado!");
                return true;
            }
            if (!isset($args[0])) {
                $sender->sendMessage("§eUse: /boss drops");
                return true;
            }
            switch (strtolower($args[0])) {

                case "drops":
                    $inv = new Window("§r§l§aBOSS§r§a - Drops");
                    $sender->addWindow($inv);
                    $name = strtolower($sender->getName());
                    $this->idw[$name] = $inv;
                    if (!$this->drops->exists($name)) {
                        return true;
                    }
                    $drops = $this->drops->get($name);
                    foreach ($drops as $k => $v) {
                        foreach ($drops[$k] as $id => $enchantment) {
                            $d = explode(":", $id);
                            $item = Item::get($d[0], $d[1], $d[2]);
                            $item->addEnchantment(Enchantment::getEnchantment((int)$enchantment[0])->setLevel((int)$enchantment[1]));
                            $inv->addItem($item);
                        }
                    }
                    break;

                case "inv":
                    if (!$sender->isOp()) {
                        $sender->sendMessage("§r§cVocê não possui permissões :D");
                        return true;
                    }
                    $sender->sendMessage("§r§eO boss foi adicionado ao seu inventário!");
                    $sender->getInventory()->addItem(Item::get(383, 57)->setCustomName("§r§a§lBOSS§r§a - Beatifull\n§7Clique no chão para spawna"));
                    break;

                default:
                    $sender->sendMessage("§r§eUse: /boss drops");
                    break;
            }
        }
        return true;
    }

    public function onInventory(InventoryTransactionEvent $e)
    {
        $trans = $e->getTransaction()->getActions();
        $inv = null;
        $player = null;
        $action = null;
        foreach($trans as $tran) {
            if ($tran instanceof SlotChangeAction) {
                $invt = $tran->getInventory();
                if ($invt instanceof Window) {
                    foreach ($invt->getViewers() as $assumed) {
                        if ($assumed instanceof Player) {
                            $player = $assumed;
                            $inv = $invt;
                            $action = $tran;
                            break;
                        }
                    }
                }
            }
        }
        if (is_null($inv)) {
            return true;
        }
        $item = $inv->getItem($action->getSlot());
        $name = strtolower($player->getName());
        $custom = $item->getCustomName();
        if (isset($this->idw[$name])) {
            $e->setCancelled(true);
            $inv = $this->idw[$name];
            if (!$invt instanceof Window) {
                $inv->setItem($action->getSlot(), $item);
                return true;
            }
            $player->getInventory()->addItem($item);
            $d = "{$item->getId()}:{$item->getDamage()}:{$item->getCount()}";
            $data = $this->drops->get($name);
            $n = 0;
            foreach ($data as $k => $v) {
                foreach ($data[$k] as $id => $enchantment) {
                    if ($id == $d) {
                        $n = $k;
                        break;
                    }
                }
            }
            unset($data[$n]);
            $inv->removeItem($item);
            $this->drops->set($name, $data);
            $this->drops->save(true);
            $this->drops->reload();
        }
        return true;
    }

    public function onClose(InventoryCloseEvent $e)
    {
        $name = strtolower($e->getPlayer()->getName());
        if (isset($this->idw[$name])) {
            unset($this->idw[$name]);
        }
    }

    public function onBreak(BlockBreakEvent $e)
    {
        $player = $e->getPlayer();
        if (mt_rand(1, 150) == 1) {
            $player->getInventory()->addItem(Item::get(383, 57)->setCustomName("§r§a§lBOSS§r§a - Beatifull\n§7Clique no chão para spawna"));
            $player->sendMessage(" \n §r§aParabens você ganhou o boss mais forte do servidor! \n ");
        }
    }

    public function onTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        $item = $e->getItem();
        $b = $e->getBlock();
        $x = $b->getX() + 0.5;
        $y = $b->getY() + 1.5;
        $z = $b->getZ() + 0.5;
        if ($item->getId() == 383 && $item->getDamage() == 57 && $item->getCustomName() == "§r§a§lBOSS§r§a - Beatifull\n§7Clique no chão para spawna") {
            $e->setCancelled(true);
            $nbt = new CompoundTag("", [
            new ListTag("Pos", [
                new DoubleTag("", $x),
                new DoubleTag("", $y),
                new DoubleTag("", $z)
            ]),
            new ListTag("Rotation", [
                new FloatTag("", $player->getYaw()),
                new FloatTag("", $player->getPitch())
            ]),
            new ByteTag("CustomNameVisible", 1),
            new StringTag("CustomName", "§r§l§aBOSS§r§a - Beatifull§r\n§r§fVida:§c 2500/2500")]);

            $ent = Entity::createEntity("Vindicator", $player->getLevel(), $nbt);
            $ent->spawnToAll();
            $ent->setMaxHealth(100);
            $ent->setHealth(100);
            $item->setCount($item->getCount() - 1);
            $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item);
        }
    }

    public function onDmg(EntityDamageEvent $e)
    {
        if ($e instanceof EntityDamageByEntityEvent) {
            $ent = $e->getEntity();
            $d = $e->getDamager();
            if ($d instanceof Vindicator) {
                if (!strpos("[" . $d->getNameTag() . "]", "§r§l§aBOSS§r§a - Beatifull§r")) {
                    $ent->setHealth($ent->getHealth() - 4);
                    return true;
                }
            }
            if ($ent instanceof Vindicator) {
                if (!strpos("[" . $ent->getNameTag() . "]", "§r§l§aBOSS§r§a - Beatifull§r")) {
                    return true;
                }
                $ent->setNameTag("§r§l§aBOSS§r§a - Beatifull§r\n§r§fVida:§c {$ent->getHealth()}/{$ent->getMaxHealth()}");
                $damage = $e->getFinalDamage();

                if ((($ent->getHealth() - round($damage)) <= 1)) {
                    $ent->kill();
                    //$ent->close();
                    $this->getServer()->broadcastMessage(" \n §aO jogador {$d->getNameTag()} §r§aconseguiu mata o boss mais forte do servidor! \n ");
                    $d->sendMessage("§r§aOs itens do boss foram pro armazenamento de itens, use:§e /boss drops.");
                    $d->sendMessage("§r§aVocê ganhou um vip grátis de 3 dias.");
                    $name = strtolower($d->getName());
                    $this->updateDrops($name);
                }
            }
        }
        return true;
    }

    public function updateDrops($name)
    {
        if (!$this->drops->exists($name)) {
            $this->drops->set($name, [
                1 => ["276:0:1" => "1:50"],
                2 => ["310:0:1" => "1:40"],
                3 => ["311:0:1" => "1:40"],
                4 => ["312:0:1" => "1:40"],
                5 => ["313:0:1" => "1:40"]
            ]);
            $this->drops->save(true);
            return true;
        }
        $drops = [];
        $data = $this->drops->get($name);
        $count = 0;
        foreach ($data as $n => $id) {
            foreach ($data[$n] as $k => $v) {
                $drops[$n] = [$k => $v];
            }
            $count = $n;
        }
        foreach ($this->ids as $k => $v) {
            $count++;
            $drops[$count] = [$k => $v];
        }
        $this->drops->set($name, $drops);
        $this->drops->save(true);
    }
}