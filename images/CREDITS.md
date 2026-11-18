# Hero photography

Sourced from [Unsplash](https://unsplash.com), which grants a free, irrevocable
licence to use the photos commercially without permission or attribution.
Credit is recorded here anyway, and because knowing where a file came from
matters when someone asks six months from now.

Each file is exported at 480w and 960w, in WebP and JPEG. `showcase-tile.php`
serves the smallest one that fits the viewport.

| File | Used on | Unsplash photo |
|---|---|---|
| `nails-*` | Home hero — Nails | https://unsplash.com/photos/1604654894610-df63bc536371 |
| `hair-*` | Home hero — Hair | https://unsplash.com/photos/1522337360788-8b13dee7a37e |
| `makeup-*` | Home hero — Make-up | https://unsplash.com/photos/1487412947147-5cebf100ffc2 |
| `skin-*` | Home hero — Skin | https://unsplash.com/photos/1570172619644-dfd03ed5d881 |
| `menu-*` | For artists — Your menu | https://unsplash.com/photos/1596462502278-27bfdc403348 |
| `studio-*` | For artists — Your hours | https://unsplash.com/photos/1521590832167-7bcbfaa6381f |
| `clients-*` | For artists — Your clients | https://unsplash.com/photos/1562322140-8baeececf3df |
| `products-*` | For artists — Your prices | https://unsplash.com/photos/1598440947619-2c35fc9aa908 |

## Replacing one

Drop a new photo in at 1200x1600 or larger, then regenerate the variants:

    php bin/showcase.php path/to/photo.jpg nails

**Do not** substitute images taken from Pinterest, Instagram or a Google image
search. Those are other people's copyrighted work, and a booking platform that
takes money is exactly the kind of site that gets a takedown notice for it.
