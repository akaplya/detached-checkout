# CommerceOptimizer Checkout — Why It Exists and What It Solves

> **Status:** This document explains an ongoing project to make Adobe Commerce checkout work when Adobe Commerce Optimizer (ACO) — not Adobe Commerce itself — is treated as the "real" place product data lives. The checkout code described here is a **solid working prototype**: it proves the whole idea works from end to end, and it already tackles the hardest parts (like handling bundle products and deciding when cart data has gone stale). It is not a finished, production-ready system yet. This document calls out the rough edges and open questions on purpose instead of hiding them — a separate requirements document lists them in full detail.

## 1. Motivation: Why Read Product Data from Commerce Optimizer at All

Before getting into what the checkout module actually does, it's worth explaining *why* a store would even want its product data to live in Commerce Optimizer instead of in Adobe Commerce's own catalog. (If the name "Adobe Commerce" is new to you: it's the platform you may already know as Magento — same core software, current name.) Everything else in this document only makes sense once this reason is clear.

### 1.1 Adobe Commerce's catalog structure is legacy and doesn't scale flexibly

Adobe Commerce stores its catalog using an older design (EAV attributes, attribute sets, a category tree with built-in limits). That design works fine for catalogs of a certain size and shape, but it's not as flexible as Commerce Optimizer's design once a catalog needs a lot of fine-grained variation — more attribute combinations, more region- or segment-specific versions of the same data, more price and content variants per product. Push Adobe Commerce's catalog structure past what it was built for, and it usually doesn't just slow down gracefully — instead you get **duplicated data**: the same underlying facts get copied across many rows and records instead of being stored once, because the older structure has no better way to express them.

### 1.2 Import and update throughput has practical limits

Adobe Commerce's product import/update process wasn't built to be rewritten nonstop at very large scale. If a catalog has millions of SKUs, or if prices and content change constantly, it gets hard to keep Adobe Commerce's own import and indexing process caught up — the speed of that process becomes the bottleneck on how up-to-date the catalog can stay. This connects directly to Section 1.1: the duplicated data that an older, less flexible structure produces at scale makes this problem worse, because every extra duplicated row is more data that each import cycle has to push through.

### 1.3 Some catalog capabilities may only exist in Commerce Optimizer

Beyond scale, some catalog features a store might need — like layered or segmented catalogs, or pricing rules with lots of nested conditions — might have no equivalent in Adobe Commerce's catalog model at all. For those features, Commerce Optimizer isn't just a faster or bigger version of the same tool — it may be the **only** place that kind of catalog setup is possible.

### 1.4 What this motivates

Put together, these three reasons are why a store might treat Commerce Optimizer, not Adobe Commerce, as the true source of product data — especially for catalogs that are huge, change constantly, or need catalog features Adobe Commerce's own structure simply can't offer. Once a store makes that choice, Adobe Commerce's checkout is left needing to *use* catalog data it no longer *owns*. That's the problem the rest of this document is about.

## 2. The Technical Problem This Creates

Adobe Commerce Optimizer (ACO) is a "headless" product-catalog service — meaning merchandising, pricing, and product content all live and get managed entirely outside Adobe Commerce. That's exactly the point made in Section 1. A storefront built this way still needs product browsing and checkout to work together as normal, but Adobe Commerce's out-of-the-box cart and order system assumes the opposite of what Section 1 just established: it assumes every product already exists as a row in Adobe Commerce's own database.

That assumption breaks the moment the "real" catalog moves to ACO:

- There is no Adobe Commerce product to add to the cart — nothing to load, price, or check stock against — yet the shopper still expects a completely normal-looking cart, checkout, and order.
- The product's identity, price, and options only exist as data fetched over the network from ACO, and that data somehow has to survive being shown, shown again, and eventually turned into a line on an order — without asking ACO for the same thing over and over on every page view.
- None of this is allowed to mean rebuilding checkout from scratch. Adobe Commerce's built-in GraphQL checkout (the add/update/remove cart mutations, cart totals, placing the order) is what storefronts already use, and it needs to keep working exactly the same way from the shopper's point of view.

## 3. What This Initiative Solves

The checkout module built for this project closes that gap: it makes Adobe Commerce's checkout pipeline work with a catalog it doesn't own, without the storefront (or most of Adobe Commerce's own core code) even noticing anything is different:

- **Connects an outside catalog to Adobe Commerce's own checkout engine.** It fetches product data from ACO whenever it's needed and builds temporary, in-memory `Product` objects that look exactly like what Adobe Commerce's cart/quote/GraphQL code expects. That means the normal `addProductsToCart`, `updateCartItems`, `removeItemFromCart`, and order-placement calls keep working as the one and only checkout API — the storefront never has to learn any ACO-specific commands.
- **Avoids asking ACO for the same product over and over.** Product data is cached for the current request, and once a cart line is actually saved, it's stored as a self-contained snapshot — so showing the cart again later never needs to contact ACO again.
- **Makes "a product that doesn't exist in the database" safe to work with.** Because there's no real database row behind these products, several things Adobe Commerce normally assumes — that a product can be loaded, that stock can be checked, that inventory can be reserved for it — have to be relaxed on purpose. That's what stops Adobe Commerce from simply rejecting or breaking on a product it can't find in its own tables.
- **Keeps carts from quietly going stale.** Every cart item now carries an expiration rule (delete it, refresh it from ACO, or just warn about it), so that old pricing or availability pulled from an outside system doesn't silently make it all the way through to a placed order.
- **Keeps all ACO-specific knowledge in one narrow place.** The code that knows ACO's specific API shape, headers, and encoding tricks is kept separate from the more general code that just handles "checkout for a catalog Adobe Commerce doesn't own" (grouping items, expiring items, cart actions). That way, the ACO connection is just a swappable detail — a different outside catalog, or even Adobe Commerce's own catalog, could sit behind that same boundary later.

In short: this project exists so that moving to an outside, headless catalog doesn't force a full rewrite of checkout. This prototype answers a question that was previously open: can Adobe Commerce's normal, unmodified checkout logic run on products that were never actually added into Adobe Commerce at all? The answer is yes — and along the way it surfaces the design questions covered in Section 4 and Section 5, which any further work on this idea will need to settle.

## 4. Product Types: How Each Kind Is Handled

The different product types checkout has to support aren't equally hard, so this section goes through them from easiest to hardest instead of treating "not a real Adobe Commerce product" as one single problem. Simple products don't need anything beyond the basic "no database row" issue already covered in Section 3. Configurable products just need narrowing down to one exact thing being bought. Bundle products are the hardest case: choosing a bundle really means buying *several* separately priced things at once, not just one — and that's exactly where ACO's way of describing products and Adobe Commerce's way of thinking about products pull furthest apart. It's tempting to treat all three the same way just because they look similar in ACO's raw data — they shouldn't be, and the rest of this section explains why.

### 4.1 Simple Products — the Baseline Case

A simple product from ACO is exactly one thing you can buy: one SKU, one price, one set of text and images. There's nothing to break apart and nothing to group together. The "no database row" problem from Section 3 still applies — its data still has to be fetched, rebuilt in memory, and snapshotted just like any other ACO product — but there's no extra *relationship* puzzle on top of that. A simple product is the easy case every other case gets compared to: one ACO product goes in, one ordinary cart line comes out, and it never has to coordinate with any other line.

### 4.2 Configurable Products — One Purchasable Unit, Reached by Narrowing Down

A configurable product looks a lot like a bundle in ACO's data — both show the shopper a set of choices — but what those choices actually *mean* is completely different, and that's what should decide how each one gets handled:

- In a bundle, every group of choices adds a separate piece that ends up in the cart *at the same time* as the others (size **and** crust **and** sauce **and** cheese). The shopper's picks add up to several separate things.
- In a configurable, the groups of choices (like size or color) don't add anything new — they just *narrow down* which one exact version the shopper wants. Picking "size: large" and "color: red" doesn't mean the cart should have a "large" line and a separate "red" line — it means the cart should have exactly one line: the one specific version that matches both picks.

Because choosing a configurable only ever results in one thing to buy, not several, the bundle approach described later (Sections 4.5–4.7) — breaking a selection into several separate, linked cart lines — doesn't apply here, and using it would be the wrong model. The right way to think about a configurable is much closer to how Adobe Commerce's own native configurable products already work: a parent product that represents the choices, and one specific child variant that actually gets priced, ordered, and fulfilled — not a bunch of cart lines standing in for a group of separate items.

### 4.3 Open Question: Configurable Selections Don't Yet Converge Consistently

Right now, a shopper can reach checkout with a configurable selection through more than one place in the storefront. Because those entry points were built at different times against the same underlying ACO data, they don't currently end up doing exactly the same thing: one correctly ends up with a real parent/variant pair, priced at the specific variant the shopper picked; the other can end up closer to the bundle-style flattening described in Section 4.2 — which is exactly what should be avoided — priced off the parent product instead of the variant the shopper actually chose. This is pointed out here because it's a real example of the Section 4.4 distinction not being followed consistently yet, not because there's any doubt about how configurables *should* work — see the requirements document for the specific gap and how it could be fixed. This is exactly the kind of loose end a prototype is supposed to surface rather than hide.

### 4.4 Why the Distinction Between These and Bundles Matters

Treating a configurable like a mini bundle (breaking its selection into several grouped cart lines) would try to solve a problem configurables don't actually have — there's no set of prices to add up and no partial-removal rule to enforce, because there's only ever one thing being bought. On the flip side, treating a bundle like a configurable (squashing it down into one line) would hide real, separately priced pieces inside a single price and SKU, throwing away the per-piece pricing and reporting detail that Sections 4.5–4.7 exist to protect. The two product types look alike in ACO's raw data — both show up as a set of options — but they represent completely different real-world purchases, and the checkout code has to tell them apart instead of treating every "looks-like-it-has-options" ACO product the same way.

### 4.5 Bundle Products: Why Adobe Commerce's Native Model Can't Be Reused

Bundles get their own detailed explanation because this is the case where ACO's way of describing products and Adobe Commerce's way of thinking about products clash the hardest — and where simply copying Adobe Commerce's native bundle idea isn't possible.

Adobe Commerce's bundle product type depends entirely on everything being properly linked together in the database: the bundle itself, its option groups, and its selections are all real rows that exist on their own and are connected by foreign keys. Checkout, pricing, and reporting all rely on being able to look those rows up again later.

None of that exists here. A bundle in ACO (say, a build-your-own pizza with size/crust/sauce/topping choices) comes back as a single combined API response — its pieces are only ever mentioned *inside* that one response. They aren't separate products you can fetch on their own. There's no way in ACO to "look up this exact bundle choice by ID," the way Adobe Commerce would look something up in its database. Once the shopper's choices leave that one API response, there's no way to ask ACO again what exactly they picked.

That's why the native bundle model doesn't just feel awkward to reuse here — it genuinely can't be reused. Adobe Commerce's bundle checkout assumes a parent-and-children relationship anchored in real, saved catalog rows, and those rows can never exist for something Adobe Commerce doesn't even consider a product outside of that one combined API response.

### 4.6 Bundle Products: The Consequence — Reinvented as Plain Cart Items

Since a bundle choice can't be stored as "one parent cart line plus links to catalog rows," the only thing that can safely be trusted to stay accurate later is whatever got captured about each chosen piece *at the moment the shopper added it to the cart*. That leads to a different design on purpose:

- Each chosen piece becomes its own ordinary, self-contained cart line — carrying everything needed to show it and price it — instead of being a pointer to something that could be looked up again later.
- Since Adobe Commerce has no built-in idea of these separate lines belonging together as one purchase, that connection has to be rebuilt and tracked by the checkout module itself, instead of relying on Adobe Commerce's bundle tables (which simply don't apply to products that aren't in the catalog).
- Anything a shopper does to a "bundle" — viewing it, changing it, removing it — is really several coordinated actions on ordinary cart lines, controlled by rules built on top of normal cart-item behavior, not by Adobe Commerce's built-in bundle logic.

### 4.7 Bundle Products: Why This Tradeoff Is the Right One

The alternative — trying to make a bundle look like a real, native Adobe Commerce bundle product — was never actually possible, because that would require the bundle and its pieces to exist as real catalog rows, which goes against the entire premise that the catalog lives in ACO, not Adobe Commerce. Given that constraint, breaking a bundle into several ordinary, independently valid cart lines is the only approach that:

- Can be priced and shown correctly without needing to ask ACO again for something it never offered as a standalone product in the first place.
- Still produces a completely normal, orderable cart and order as far as Adobe Commerce is concerned, because every line in it is just an ordinary cart item for pricing, tax, and order purposes.
- Keeps the "this isn't a real bundle" problem confined to one small, purpose-built piece of code (grouping the chosen lines and making sure they move together) instead of forcing every part of checkout, order management, and reporting to learn a brand-new kind of product.

This tradeoff costs something: "bundle" becomes a rule this module enforces on top of ordinary cart items, rather than something Adobe Commerce itself understands. That's why keeping a bundle's pieces together — deciding what belongs with what, and what has to happen to the whole group when one piece is removed or refreshed — has to be actively maintained by this module for as long as the catalog stays outside Adobe Commerce.

## 5. Cart-Item Expiration — Why a Snapshot Cart Needs a Freshness Policy

### 5.1 The problem a snapshot cart creates

Every cart line this module creates is a **snapshot** — basically a photo of whatever ACO said at the exact moment the shopper added it. That's what makes showing the cart again cheap (Section 3: no need to ask ACO again). But a snapshot, by definition, is a copy, and copies can drift out of sync with the original. ACO's pricing and product info are managed completely separately and can change at any time — a price can go up or down, a product can get discontinued, availability can change — while the shopper's cart line just keeps showing whatever was true the moment it was added, for as long as the cart sits untouched.

A cart with no freshness rule would let a shopper carry old, possibly wrong pricing or availability all the way to placing the order. That's the real risk here: not "the data might be wrong once," but "nothing would ever notice or fix it before money actually changes hands."

### 5.2 Expiration time: a deliberately short trust window

Instead of trusting a snapshot forever, every cart line gets a lifetime — after that, it's no longer taken at face value. This is a limit on how long a price/availability snapshot can be trusted before it needs to be double-checked. How long that window is isn't a fixed number baked into the code — it's a choice each store makes: a short window keeps carts closer to what's actually true in ACO right now, at the cost of more re-fetching and friction; a long window is more convenient for the shopper (a cart left overnight "just works"), at the cost of trusting older data for longer. Making the length something each store can configure — instead of one hardcoded number — lets every store tune that tradeoff to match how often its own catalog actually changes.

### 5.3 Why three different strategies, not one

Once a cart line's trust window runs out, there's no single universally "correct" response — different businesses, and even different moments in a rollout, reasonably want different things to happen:

- **Delete the item.** The safest choice, and the default: an expired line just isn't trusted enough to keep, so it gets removed, leaving the shopper with an accurate cart instead of a stale one. This picks correctness over convenience — the shopper loses that line, but is never at risk of buying based on old data.
- **Reload the item.** Instead of throwing the line away, re-fetch the same SKU from ACO and refresh the snapshot in place. This is more convenient than deleting — the shopper's cart still has the same items, just refreshed — but it only works if that SKU is something ACO can actually be asked about again on its own (see Section 5.4).
- **Warn, but don't touch anything.** Leave the line exactly as it is, but flag it as expired instead of silently changing or removing it. This is for cases where someone wants a human to make the call instead of the system deciding automatically — the shopper is told the line needs attention, and checkout is deliberately blocked from finishing while that flag is up, so a possibly-stale line can't quietly turn into an order.

Offering all three as choices — instead of picking one "right" answer — reflects the fact that "what should happen to stale cart data" is really a business decision (how much to favor shopper convenience versus pricing/availability accuracy), and this module shouldn't make that decision for every store on its own.

### 5.4 Where this interacts with bundle handling

The reload option's whole idea — "this SKU can be asked about again" — doesn't always hold true. As Section 4.5 explained, a bundle's individual pieces aren't separately fetchable ACO products; they only ever existed inside the one combined response that produced them. Asking ACO about a bundle piece's SKU on its own just isn't something ACO can answer. That means the reload option's benefit — refresh in place, keep the same cart contents — is only available for genuinely standalone products. Expiration rules for a bundle's pieces have to work around the same "these lines can't be looked up again on their own" limitation that already shapes how they get removed and updated (Sections 4.6 and 4.7) — not just how they get added.

### 5.5 Why expiration is re-evaluated on read, not on a timer

Checking whether something has expired right when the cart is loaded — instead of on some scheduled background job — means a shopper never ends up seeing data that's stale for longer than intended just because of how often that background job happens to run; the check is exactly as fresh as the last time anyone actually looked at the cart. It also means expiration doesn't depend on some separate scheduled process being set up correctly and actually running — it's just a natural part of reading the cart at all, which fits how much of this module's other behavior (Section 3) is already built around "do the right thing the moment something is read," instead of relying on a separate maintenance job running in the background.

## 6. Tax and Shipping — Riding on Adobe Commerce's Own Calculation, Unmodified

Section 4.7 already made one promise about the tradeoff bundles make: "every line in it is just an ordinary cart item for pricing, tax, and order purposes." This section spells out what that promise actually costs once real tax rules and real shipping methods are pointed at a decomposed bundle.

### 6.1 There is no bundle-aware tax or shipping code at all

This is worth stating plainly instead of leaving it implied: nothing in this module plugs into Adobe Commerce's Tax module or its shipping-rate calculation. Every hydrated product — whether it's a genuine standalone simple product or one piece of a decomposed bundle — goes through the same product hydrator, and that hydrator does exactly two tax/shipping-relevant things: it sets the product's tax class to the store's configured *default product tax class* as a baseline, then replays whatever ACO sent back as generic product attributes on top of it — so a component that ACO describes with its own `tax_class_id` attribute gets that value instead of the default, the same generic passthrough that carries over any other ACO attribute. It never sets a weight at all, though, under any circumstance. Adobe Commerce's own Tax and Shipping modules then run completely untouched on top of whatever quote items they're handed. That's a deliberate consequence of Section 4.6's design, not an oversight: once a bundle's pieces are "just ordinary cart items," the only honest thing to do is let ordinary cart-item logic — tax and shipping included — apply to them exactly as it would to anything else, rather than quietly special-casing them somewhere no one would think to look.

### 6.2 What that means for tax

Because every decomposed piece is its own line with its own price snapshot (Section 4.6), Adobe Commerce computes tax the same way it would for any ordinary multi-item cart: rate × price × qty, per line, then summed. In the build-your-own-pizza example used throughout the scenario docs, only the size selection actually carries a non-zero price — crust, sauce, cheese, and topping all come back from ACO priced at zero — so in practice tax ends up computed against whichever pieces ACO happened to price, added together, rather than against one single number that represents "the bundle." If ACO ever priced more than one piece (an upcharge topping, say), that upcharge is taxed as its own ordinary line at whichever tax class it ended up with — the store default, unless ACO supplied its own `tax_class_id` for that piece — same rate and rounding rules as everything else — nothing about it being "part of a bundle" changes how it's taxed.

### 6.3 What that means for shipping

Shipping runs the same way: whatever Adobe Commerce's core shipping calculation would do with an ordinary set of quote items, it does here too, unmodified. Every scenario doc in this project exercises that path with `flatrate` shipping, which is what's been verified end to end so far.

## 7. Shopping Cart Promotions

Adobe Commerce's promotions engine (Cart Price Rules) runs untouched here too, the same way Tax and Shipping do (Section 6.1): nothing in this module plugs into it or overrides it. It evaluates each decomposed piece as its own ordinary quote item, the same as it would any standalone simple product, without any awareness of `items_group` or of the bundle those pieces came from.
