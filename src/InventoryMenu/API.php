<?php

declare(strict_types=1);

namespace InventoryMenu;


use InventoryMenu\event\InventoryClickEvent;
use pocketmine\block\BlockIds;
use pocketmine\event\Listener;
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
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class API extends PluginBase implements Listener
{
    /** @var  array */
    private $chest;
    /** @var  API */
    public static $instance;

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onLoad(): void
    {
        self::$instance = $this;
    }

    /**
     * @return API
     */
    public static function getInstance(): API
    {
        return self::$instance;
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $e): void
    {
        $packet = $e->getPacket();
        $player = $e->getPlayer();
        if ($packet instanceof InventoryTransactionPacket) {
            if (!isset($this->chest[$player->getName()]) or !isset($packet->actions[0])) return;
            if ($packet->transactionType === 0 && $this->chest[$player->getName()]) {
                /** @var NetworkInventoryAction $action */
                $action = $packet->actions[0];
                $this->getServer()->getPluginManager()->callEvent($ev = new InventoryClickEvent($this, $player, $action->oldItem, $action->inventorySlot, $this->chest[$player->getName()][0]));
                if (!$ev->isCancelled()) {
                    $pk = new ContainerClosePacket;
                    $pk->windowId = WindowTypes::MINECART_CHEST;
                    $player->dataPacket($pk);
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

    public function onPlayerQuit(PlayerQuitEvent $e): void
    {
        $this->clearData($e->getPlayer());
    }

    /**
     * @param Player $player target player
     * @param Item[] $items  items array
     * @param int $id        chest id
     * @param string $title  chest title
     * @param bool $double   double chest or not? default = false
     */
    public function createChest(Player $player, array $items, int $id, string $title = "", bool $double = false): void
    {
        $this->clearData($player);

        $v3 = new Vector3(intval($player->x), intval($player->y) - 2, intval($player->z));
        $this->chest[$player->getName()] = [$id, $v3];
        $this->updateBlock($player, 54, $v3);

        $nbt = new NBT(NBT::LITTLE_ENDIAN);
        if ($double) {
            $this->chest[$player->getName()][2] = true;
            $this->updateBlock($player, BlockIds::CHEST, new Vector3($v3->x + 1, $v3->y, $v3->z));
            $nbt->setData($title === "" ?
                new CompoundTag("", [
                    new IntTag("pairx", $v3->x + 1),
                    new IntTag("pairz", $v3->z)
                ]) : new CompoundTag("", [
                    new StringTag("CustomName", $title),
                    new IntTag("pairx", $v3->x + 1),
                    new IntTag("pairz", $v3->z)
                ])
            );
        } else {
            $nbt->setData($title === "" ? new CompoundTag("", []) : new CompoundTag("", [new StringTag("CustomName", $title)]));
        }
        $pk = new BlockEntityDataPacket;
        $pk->x = $v3->x;
        $pk->y = $v3->y;
        $pk->z = $v3->z;
        $pk->namedtag = $nbt->write(true);
        $player->dataPacket($pk);

        if ($double) usleep(51000);

        $pk1 = new ContainerOpenPacket;
        $pk1->windowId = WindowTypes::MINECART_CHEST;
        $pk1->type = WindowTypes::CONTAINER;
        $pk1->x = $v3->x;
        $pk1->y = $v3->y;
        $pk1->z = $v3->z;
        $player->dataPacket($pk1);

        $pk2 = new InventoryContentPacket;
        $pk2->windowId = WindowTypes::MINECART_CHEST;
        $pk2->items = $items;
        $player->dataPacket($pk2);
    }

    /**
     * @param Player $player
     * @param int $id
     * @param Vector3 $v3
     */
    private function updateBlock(Player $player, int $id, Vector3 $v3): void
    {
        $pk = new UpdateBlockPacket;
        $pk->x = $v3->x;
        $pk->y = $v3->y;
        $pk->z = $v3->z;
        $pk->blockId = $id;
        $pk->blockData = UpdateBlockPacket::FLAG_ALL;
        $player->dataPacket($pk);
    }

    /**
     * @param Player $player
     */
    private function clearData(Player $player): void
    {
        if (isset($this->chest[$player->getName()])) unset($this->chest[$player->getName()]);
    }
}