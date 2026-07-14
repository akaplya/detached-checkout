# Bundle Product Checkout — GraphQL Test Steps

Guest checkout of `DOMINOS-BYO-PIZZA` (a `ComplexProductView` bundle in ACO) through to order placement.  
Endpoint: `http://acaco.local/graphql`  
All requests: `POST`, `Content-Type: application/json`.

---

## Bundle reference

| Option   | Selected value         | Encoded option value ID |
|----------|------------------------|-------------------------|
| Size     | Small (10")            | `YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tc2l6ZS1zbWFsbCIsICJxdHkiOjEuMH0=` |
| Crust    | Hand Tossed            | `YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tY3J1c3QtaGFuZC10b3NzZWQiLCAicXR5IjoxLjB9` |
| Sauce    | Robust Tomato          | `YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tc2F1Y2Utcm9idXN0LXRvbWF0byIsICJxdHkiOjEuMH0=` |
| Cheese   | Normal Cheese          | `YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tY2hlZXNlLW5vcm1hbCIsICJxdHkiOjEuMH0=` |
| Toppings | Pepperoni              | `YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tdG9wcGluZy1wZXBwZXJvbmkiLCAicXR5IjoxLjB9` |

Each ID decodes as `base64(bundle_item/{"sku":"<component-sku>","qty":1.0})`.

---

## Step 1 — Create a guest cart

```graphql
mutation {
  createEmptyCart
}
```

**Expected response**

```json
{
  "data": {
    "createEmptyCart": "<CART_ID>"
  }
}
```

Save `<CART_ID>` — it is required in every subsequent step.

---

## Step 2 — Add the bundle product

The bundle is sent as a single `sku` with five `selected_options`.  
The plugin expands it into five individual `SimpleCartItem` rows, one per component.

```graphql
mutation {
  addProductsToCart(
    cartId: "<CART_ID>"
    cartItems: [
      {
        sku: "DOMINOS-BYO-PIZZA"
        quantity: 1
        selected_options: [
          "YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tc2l6ZS1zbWFsbCIsICJxdHkiOjEuMH0="
          "YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tY3J1c3QtaGFuZC10b3NzZWQiLCAicXR5IjoxLjB9"
          "YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tc2F1Y2Utcm9idXN0LXRvbWF0byIsICJxdHkiOjEuMH0="
          "YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tY2hlZXNlLW5vcm1hbCIsICJxdHkiOjEuMH0="
          "YnVuZGxlX2l0ZW0veyJza3UiOiJieW8tdG9wcGluZy1wZXBwZXJvbmkiLCAicXR5IjoxLjB9"
        ]
      }
    ]
  ) {
    cart {
      id
    }
    user_errors {
      message
      code
    }
  }
}
```

**Expected response**

```json
{
  "data": {
    "addProductsToCart": {
      "cart": { "id": "<CART_ID>" },
      "user_errors": []
    }
  }
}
```

---

## Step 3 — Set shipping address

```graphql
mutation {
  setShippingAddressesOnCart(input: {
    cart_id: "<CART_ID>"
    shipping_addresses: [
      {
        address: {
          firstname:    "John"
          lastname:     "Doe"
          street:       ["123 Main St"]
          city:         "Austin"
          region:       "TX"
          postcode:     "78701"
          country_code: "US"
          telephone:    "5125550100"
        }
      }
    ]
  }) {
    cart {
      shipping_addresses {
        available_shipping_methods {
          carrier_code
          method_code
          carrier_title
          method_title
        }
      }
    }
  }
}
```

**Expected response** — at least one available method:

```json
{
  "data": {
    "setShippingAddressesOnCart": {
      "cart": {
        "shipping_addresses": [
          {
            "available_shipping_methods": [
              {
                "carrier_code": "flatrate",
                "method_code": "flatrate",
                "carrier_title": "Flat Rate",
                "method_title": "Fixed"
              }
            ]
          }
        ]
      }
    }
  }
}
```

---

## Step 4 — Set guest email

```graphql
mutation {
  setGuestEmailOnCart(input: {
    cart_id: "<CART_ID>"
    email:   "test@example.com"
  }) {
    cart {
      email
    }
  }
}
```

**Expected response**

```json
{
  "data": {
    "setGuestEmailOnCart": {
      "cart": { "email": "test@example.com" }
    }
  }
}
```

---

## Step 5 — Set shipping method

Use a `carrier_code` / `method_code` pair returned by Step 3.

```graphql
mutation {
  setShippingMethodsOnCart(input: {
    cart_id: "<CART_ID>"
    shipping_methods: [
      {
        carrier_code: "flatrate"
        method_code:  "flatrate"
      }
    ]
  }) {
    cart {
      shipping_addresses {
        selected_shipping_method {
          carrier_code
          method_code
        }
      }
    }
  }
}
```

**Expected response**

```json
{
  "data": {
    "setShippingMethodsOnCart": {
      "cart": {
        "shipping_addresses": [
          {
            "selected_shipping_method": {
              "carrier_code": "flatrate",
              "method_code": "flatrate"
            }
          }
        ]
      }
    }
  }
}
```

---

## Step 6 — Set billing address

```graphql
mutation {
  setBillingAddressOnCart(input: {
    cart_id: "<CART_ID>"
    billing_address: {
      address: {
        firstname:    "John"
        lastname:     "Doe"
        street:       ["123 Main St"]
        city:         "Austin"
        region:       "TX"
        postcode:     "78701"
        country_code: "US"
        telephone:    "5125550100"
      }
    }
  }) {
    cart {
      available_payment_methods {
        code
        title
      }
    }
  }
}
```

**Expected response** — at least one available payment method:

```json
{
  "data": {
    "setBillingAddressOnCart": {
      "cart": {
        "available_payment_methods": [
          {
            "code":  "checkmo",
            "title": "Check / Money order"
          }
        ]
      }
    }
  }
}
```

---

## Step 7 — Set payment method

Use a `code` returned by Step 6.

```graphql
mutation {
  setPaymentMethodOnCart(input: {
    cart_id: "<CART_ID>"
    payment_method: {
      code: "checkmo"
    }
  }) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
```

**Expected response**

```json
{
  "data": {
    "setPaymentMethodOnCart": {
      "cart": {
        "selected_payment_method": { "code": "checkmo" }
      }
    }
  }
}
```

---

## Step 8 — Inspect cart (optional verification)

Verify bundle items, ACO names, group metadata, and grand total before placing the order.

```graphql
{
  cart(cart_id: "<CART_ID>") {
    items {
      uid
      quantity
      ... on SimpleCartItem {
        aco_product_name
        cart_item_group {
          sku
          name
          selected_options {
            label
            values {
              label
            }
          }
        }
      }
    }
    prices {
      grand_total {
        value
        currency
      }
    }
  }
}
```

**Expected response** — five `SimpleCartItem` rows sharing the same `cart_item_group`:

```json
{
  "data": {
    "cart": {
      "items": [
        {
          "uid": "...",
          "quantity": 1,
          "aco_product_name": "Build Your Own Pizza - Small (10\")",
          "cart_item_group": {
            "sku": "DOMINOS-BYO-PIZZA",
            "name": "Build Your Own Pizza",
            "selected_options": [
              { "label": "Size",     "values": [{ "label": "Small (10\")" }] },
              { "label": "Crust",    "values": [{ "label": "Hand Tossed" }] },
              { "label": "Sauce",    "values": [{ "label": "Robust Inspired Tomato Sauce" }] },
              { "label": "Cheese",   "values": [{ "label": "Normal Cheese" }] },
              { "label": "Toppings", "values": [{ "label": "Pepperoni" }] }
            ]
          }
        }
      ],
      "prices": {
        "grand_total": { "value": 32.74, "currency": "USD" }
      }
    }
  }
}
```

---

## Step 9 — Place order

```graphql
mutation {
  placeOrder(input: {
    cart_id: "<CART_ID>"
  }) {
    order {
      order_number
    }
  }
}
```

**Expected response**

```json
{
  "data": {
    "placeOrder": {
      "order": {
        "order_number": "000000004"
      }
    }
  }
}
```

A non-empty `order_number` confirms the order was created successfully.  
The cart is consumed — repeating this mutation with the same `<CART_ID>` will return an error.
