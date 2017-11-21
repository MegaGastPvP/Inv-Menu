<?php

declare(strict_types=1);

namespace InventoryMenu\event;


use InventoryMenu\API;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\item\Item;
use pocketmine\Player;

class InventoryClickEvent extends PluginEvent implements Cancellable
{
    public static $handlerList = null;
    /** @var  Player $player */
    private $player;
    /** @var  Item $item */
    private $item;
    /** @var  int $chestId */
    private $slot;
    /** @var  int $slot */
    private $chestId;

    public function __construct(API $plugin, Player $player, Item $item, int $slot, int $chestId)
    {
        parent::__construct($plugin);
        $this->player = $player;
        $this->item = $item;
        $this->slot = $slot;
        $this->chestId = $chestId;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getSlot(): int
    {
        return $this->slot;
    }

    public function getChestId(): int
    {
        return $this->chestId;
    }
}