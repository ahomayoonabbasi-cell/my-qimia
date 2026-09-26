# Qimia Intelligence Lab 1.14.2

Base: Qimia Intelligence Lab 1.14.1.

This release changes only the default active tab of the unified personalized
product shelf. When `Related to your interests` contains eligible products, that
group is selected on the shelf's first render on both desktop and mobile.

The visual tab order is unchanged. If a shopper manually chooses another tab,
background data refreshes preserve that choice. If the Related group is absent,
the shelf falls back to Buy Again, then Recently Viewed on Home, then the first
valid group, matching the existing availability-safe behavior.

No recommendation generation, ranking, product hydration, stock, price, cart,
checkout, AI, My Qimia, cashback, Arabic card-height, CSS or WooCommerce logic
was changed. Plugin version 1.14.2 provides an asset cache-bust for the updated
personalization JavaScript.
