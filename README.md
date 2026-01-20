Magpie Developer Challenge – Data Acquisition Task

This repository contains my submission for the Magpie Developer Challenge.
It implements a PHP web scraper that collects smartphone product data from the provided Magpie test website.

Overview

The scraper navigates through all available pages on the challenge website and extracts structured product information including:

Title (including storage capacity)

Price

Image URL

Storage capacity (in MB)

Colour

Availability text

Availability status (boolean)

Shipping text

Shipping date (in YYYY-MM-DD format)

The extracted data is written to output.json in the project root.

Setup Instructions

Requirements:

PHP 8.1 or higher

Composer (https://getcomposer.org)

Installation:

Clone the repository:
```bash
git clone https://github.com/<ayaan117>/magpie-developer-challenge.git
cd magpie-developer-challenge
```

Install dependencies:
```bash
composer install
```

Running the Scraper

CLI Mode:
```bash
php src/Scrape.php
```

Web UI Mode:
```bash
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser. The UI provides a user-friendly interface to:
- View all scraped products in a responsive grid
- See product details including price, color, storage capacity, and availability
- Run the scraper directly from the browser
- Track statistics about available products

This will generate an `output.json` file in the root directory.

Example Output

[
{
"title": "iPhone 11 Pro 64 GB",
"price": 799.99,
"imageUrl": "https://www.magpiehq.com/images/iphone-11-pro.png
",
"capacityMB": 65536,
"colour": "green",
"availabilityText": "Out of Stock",
"isAvailable": false,
"shippingText": null,
"shippingDate": null
},
{
"title": "iPhone 12 Pro Max 128GB",
"price": 1099.99,
"imageUrl": "https://www.magpiehq.com/images/iphone-12-pro.png
",
"capacityMB": 131072,
"colour": "sky blue",
"availabilityText": "In Stock Online",
"isAvailable": true,
"shippingText": "Delivery by 2025-10-13",
"shippingDate": "2025-10-13"
}
]

Approach

Pagination support: The scraper automatically follows and processes all pages until no further products are found.

Data extraction: Uses CSS selectors and text parsing to extract relevant fields from the HTML structure.

Normalisation: Converts capacities (GB to MB) and ensures consistent numeric and date formats.

Duplicate filtering: Ensures products are unique by combining title, capacity, and colour as a unique key.

Delivery dates: Converts formatted dates such as "19 Oct 2025" to "2025-10-19".

Flexible parsing: Handles both structured product cards and fallback text-based HTML if required.

Technical Details

The scraper is implemented in PHP 8 using the following libraries:

guzzlehttp/guzzle – for making HTTP requests

symfony/dom-crawler – for parsing HTML content

symfony/css-selector – for selecting HTML elements

A debug mode can be enabled by setting DEBUG = true in Scrape.php. This writes parsed HTML snippets into a debug/ folder for inspection during development.

Project Structure

magpie-developer-challenge/
├── src/
│ ├── Product.php
│ ├── Scrape.php
│ └── ScrapeHelper.php
├── index.php
├── composer.json
├── composer.lock
├── output.json
└── README.md
