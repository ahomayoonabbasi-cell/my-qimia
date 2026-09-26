# Qimia Intelligence Lab 1.14.1

Base: Qimia Intelligence Lab 1.14.0 supplied on 2026-09-21.

This is a narrowly scoped Arabic product-card layout correction. No commerce,
recommendation, AI, personalization, cashback, inventory, translation, hero or
navigation logic changes in this release.

## Fix

The horizontal product shelves use a flex rail. The shared card skin also used
`height: 100%`. In a flex row, that explicit cross-size can prevent the normal
stretch behavior from equalizing cards when Arabic or mixed Arabic/English copy
makes one card intrinsically taller than another.

On RTL/Arabic Qimia shells only, product rails now explicitly use cross-axis
stretch and direct `.qil-product-card` children use `height: auto` plus
`align-self: stretch`. Each rail therefore takes the tallest card as its shared
height on both desktop and mobile. The existing flex column inside each card
continues to place purchase/compare/AI controls at the bottom.

No fixed card height and no JavaScript height measurement were added, so the
layout remains responsive to font rendering, prices, labels and viewport width.
English remains on the existing rules.

## Deployment

Replace the existing Qimia Intelligence Lab plugin. Version 1.14.1 changes the
asset version so WordPress/CDN caches can distinguish the corrected CSS. Purge
page/CDN caches after replacement if an upstream cache ignores query versions.
