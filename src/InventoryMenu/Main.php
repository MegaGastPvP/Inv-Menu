<?php

declare(strict_types=1);

namespace InventoryMenu;


use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\BlockEntityDataPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener
{

    /** @var  array */
    private $chest;

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $e): void
    {
        $packet = $e->getPacket();
        $player = $e->getPlayer();
        if ($packet instanceof InventoryTransactionPacket) {
            if (!isset($this->chest[$player->getName()]) or !isset($packet->actions[0])) return;
            /** @var NetworkInventoryAction $action */
            $action = $packet->actions[0];
            //NOTE: now you can get item from $packet->actions
            if ($packet->transactionType === 0 && $this->chest[$player->getName()]) {
                $pk = new ContainerClosePacket();
                $pk->windowId = 10;
                $player->dataPacket($pk);
                switch ($this->chest[$player->getName()][0]) { //chest id
                    case 1:
                        switch ($action->inventorySlot) {
                            case 0:
                                $player->sendMessage("You select stone block");
                                break;
                            case 1:
                                $player->sendMessage("You select grass block");
                                break;
                        }
                        break;
                    case 2:
                        switch ($action->inventorySlot) {
                            case 0:
                                $player->sendMessage("You select sky wars");
                                break;
                            case 1:
                                $player->sendMessage("You select hunger games");
                                break;
                        }
                        break;
                }
            }
        } elseif ($packet instanceof ContainerClosePacket) {
            if (!isset($this->chest[$player->getName()])) return;
            /** @var Vector3 $v3 */
            $v3 = $this->chest[$player->getName()][1];
            $this->updateBlock($player, $player->getLevel()->getBlock($v3)->getId(), $v3);
            if (isset($this->chest[$player->getName()][2])) {
                $v3 = $v3->setComponents($v3->x + 1, $v3->y, $v3->z);
                $this->updateBlock($player, $player->getLevel()->getBlock($v3)->getId(), $v3);
            }
            $this->clearData($player);
        }
    }

    public function onClick(PlayerInteractEvent $e): void
    {
        if ($e->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $player = $e->getPlayer();
            switch ($e->getItem()->getId()) {
                case Item::COMPASS:
                    $this->createChest($player, [Item::get(1, 0, 1), Item::get(2, 0, 1)], 1, "Select block");
                    break;
                case Item::CLOCK:
                    $this->createChest($player, [Item::get(267, 0, 1)->setCustomName("§eSkyWars"), Item::get(276, 0, 1)->setCustomName("§aHungerGames")], 2, "Select mini game", true);
                    break;
            }
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $e): void
    {
        $inv = $e->getPlayer()->getInventory();
        $inv->clearAll();
        $inv->setItem(0, Item::get(Item::COMPASS, 0, 1)->setCustomName("Select block"));
        $inv->setItem(1, Item::get(Item::CLOCK, 0, 1)->setCustomName("Select mini game"));
    }

    public function onPlayerQuit(PlayerQuitEvent $e): void
    {
        $this->clearData($e->getPlayer());
    }

    /**
     * @param Player $player
     * @param array $items  items array
     * @param int $id       chest id
     * @param string $title chest title
     * @param bool $double  double chest or not? default = false
     */
    public function createChest(Player $player, array $items, int $id, string $title, bool $double = false): void
    {
        $this->clearData($player);

        $v3 = $this->getVector($player);
        $this->chest[$player->getName()] = [$id, $v3];
        $this->updateBlock($player, 54, $v3);

        $nbt = new NBT(NBT::LITTLE_ENDIAN);
        if ($double) {
            $this->chest[$player->getName()][2] = true;
            $this->updateBlock($player, 54, new Vector3($v3->x + 1, $v3->y, $v3->z));
            $nbt->setData(new CompoundTag(
                "", [
                    new StringTag("CustomName", $title),
                    new IntTag("pairx", $v3->x + 1),
                    new IntTag("pairz", $v3->z)
                ]
            ));
        } else {
            $nbt->setData(new CompoundTag("", [new StringTag("CustomName", $title)]));
        }
        $pk = new BlockEntityDataPacket;
        $pk->x = $v3->x;
        $pk->y = $v3->y;
        $pk->z = $v3->z;
        $pk->namedtag = $nbt->write(true);
        $player->dataPacket($pk);

        if ($double) usleep(51000);

        $pk1 = new ContainerOpenPacket;
        $pk1->windowId = 10;
        $pk1->type = 0;
        $pk1->x = $v3->x;
        $pk1->y = $v3->y;
        $pk1->z = $v3->z;
        $player->dataPacket($pk1);

        $pk2 = new InventoryContentPacket();
        $pk2->windowId = 10;
        $pk2->items = $items;
        $player->dataPacket($pk2);
    }

    public function updateBlock(Player $player, int $id, Vector3 $v3): void
    {
        $pk = new UpdateBlockPacket;
        $pk->x = $v3->x;
        $pk->y = $v3->y;
        $pk->z = $v3->z;
        $pk->blockId = $id;
        $pk->blockData = 0xb << 4 | (0 & 0xf);
        $player->dataPacket($pk);
    }

    public function getVector(Player $player): Vector3
    {
        return new Vector3(intval($player->x), intval($player->y) - 2, intval($player->z));
    }

    public function clearData(Player $player): void
    {
        if (isset($this->chest[$player->getName()])) unset($this->chest[$player->getName()]);
    }
}