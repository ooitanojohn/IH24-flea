<?php

namespace Eccube\Service\PurchaseFlow;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Eccube\Entity\ItemInterface;
use Eccube\Entity\Master\OrderItemType;
use Eccube\Entity\Order;

class ItemCollection extends ArrayCollection
{
    protected $type;

    public function __construct($Items, $type = null)
    {
        $this->type = is_null($type) ? Order::class : $type;

        if ($Items instanceof Collection) {
            $Items = $Items->toArray();
        }
        parent::__construct($Items);
    }

    public function reduce(\Closure $func, $initial = null)
    {
        return array_reduce($this->toArray(), $func, $initial);
    }

    // 明細種別ごとに返すメソッド作る
    public function getProductClasses()
    {
        return $this->filter(
            function (ItemInterface $ShipmentItem) {
                return $ShipmentItem->isProduct();
            });
    }

    public function getDeliveryFees()
    {
        return $this->filter(
            function (ItemInterface $ShipmentItem) {
                return $ShipmentItem->isDeliveryFee();
            });
    }

    public function getCharges()
    {
        return $this->filter(
            function (ItemInterface $ShipmentItem) {
                return $ShipmentItem->isCharge();
            });
    }

    public function getDiscounts()
    {
        return $this->filter(
            function (ItemInterface $ShipmentItem) {
                return $ShipmentItem->isDiscount();
            });
    }

    /**
     * 同名の明細が存在するかどうか.
     *
     * TODO 暫定対応. 本来は明細種別でチェックする.
     */
    public function hasProductByName($productName)
    {
        $ShipmentItems = $this->filter(
            function (ItemInterface $ShipmentItem) use ($productName) {
                /* @var ShipmentItem $ShipmentItem */
                return $ShipmentItem->getProductName() == $productName;
            });

        return !$ShipmentItems->isEmpty();
    }

    /**
     * 指定した受注明細区分の明細が存在するかどうか.
     *
     * @param OrderItemType $OrderItemType 受注区分
     *
     * @return bool
     */
    public function hasItemByOrderItemType($OrderItemType)
    {
        $filteredItems = $this->filter(function (ItemInterface $ShipmentItem) use ($OrderItemType) {
            /* @var ShipmentItem $ShipmentItem */
            return $ShipmentItem->getOrderItemType() && $ShipmentItem->getOrderItemType()->getId() == $OrderItemType->getId();
        });

        return !$filteredItems->isEmpty();
    }

    public function getType()
    {
        return $this->type;
    }

    public function sort()
    {
        $Items = $this->toArray();
        usort($Items, function (ItemInterface $a, ItemInterface $b) {
            if ($a->getOrderItemType() === $b->getOrderItemType()) {
                return ($a->getId() < $b->getId()) ? -1 : 1;
            } elseif ($a->isProduct()) {
                return -1;
            } elseif ($a->isDeliveryFee()) {
                if ($b->isProduct()) {
                    return 1;
                }

                return -1;
            } elseif ($a->isCharge()) {
                if ($b->isDeliveryFee() || $b->isProduct()) {
                    return 1;
                }

                return -1;
            } elseif ($a->isDiscount()) {
                if (!$b->isTax()) {
                    return 1;
                }

                return -1;
            } elseif ($a->isTax()) {
                return 1;
            }

            return 0;
        });

        return new self($Items);
    }
}