<?php

namespace App;

class Product
{
    public string $title;
    public ?float $price;
    public ?string $imageUrl;
    public ?int $capacityMB;
    public ?string $colour;
    public ?string $availabilityText;
    public bool $isAvailable;
    public ?string $shippingText;
    public ?string $shippingDate;

    public function __construct(array $data)
    {
        $this->title            = (string)($data['title'] ?? '');
        $this->price            = isset($data['price']) ? (float)$data['price'] : null;
        $this->imageUrl         = $data['imageUrl'] ?? null;
        $this->capacityMB       = isset($data['capacityMB']) ? (int)$data['capacityMB'] : null;
        $this->colour           = $data['colour'] ?? null;
        $this->availabilityText = $data['availabilityText'] ?? null;
        $this->isAvailable      = (bool)($data['isAvailable'] ?? false);
        $this->shippingText     = $data['shippingText'] ?? null;
        $this->shippingDate     = $data['shippingDate'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'title'            => $this->title,
            'price'            => $this->price,
            'imageUrl'         => $this->imageUrl,
            'capacityMB'       => $this->capacityMB,
            'colour'           => $this->colour,
            'availabilityText' => $this->availabilityText,
            'isAvailable'      => $this->isAvailable,
            'shippingText'     => $this->shippingText,
            'shippingDate'     => $this->shippingDate,
        ];
    }
}
