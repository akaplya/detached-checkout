## Create an empty cart
```
mutation {
  createGuestCart(input: {cart_uid: "fa67c95ddb4942ada5df503fa91d3db3"}) {
    cart {
      id
    }
  }
}
```

## Add product to cart
```
mutation {
  addProductsToCart (
    cartId: "fa67c95ddb4942ada5df503fa91d3db3",
    cartItems: [
      {sku: "ADB160" quantity: 1}
    ]
  ) {
    cart {
      id
      itemsV2 {
        
        total_count
        items {
          uid 
          __typename

          ... on SimpleCartItem {
            prices {
              price {
                value
                currency
              }
            }
          }
          __typename
        }
      }
    }
  }
}
```

## Get Cart by Id
```
query {
  cart(cart_id: "fa67c95ddb4942ada5df503fa91d3db3") {
    id
    prices {
      grand_total {
        currency
        value
      }
    }
    itemsV2 {
      __typename
      total_count
      items {
        __typename
        prices {
          row_total {
            currency
            value
          }
        }
      }
    }
  }
}
```

## Set an email
```
mutation {
  setGuestEmailOnCart(
    input: {
      cart_id: "fa67c95ddb4942ada5df503fa91d3db3"
      email: "guest@example.com"
    }
  ) {
    cart {
      email
    }
  }
}
```

## Set Shipping Address
```
mutation {
  setShippingAddressesOnCart(
    input: {
      cart_id: "fa67c95ddb4942ada5df503fa91d3db3"
      shipping_addresses: {
        address: {
          firstname: "John"
          lastname: "Doe"
          street: ["123 Main St"]
          city: "Los Angeles"
          region: "CA"
          postcode: "90001"
          country_code: "US"
          telephone: "1234567890"
        }
      }
    }
  ) {
    cart {
      billing_address {
        firstname
        lastname
        street
        city
        region {
          code
        }
        postcode
        country {
          code
        }
      }
    }
  }
}
```

## Set Billing Address (Same as Shipping)
```
mutation {
  setBillingAddressOnCart(
    input: {
      cart_id: "fa67c95ddb4942ada5df503fa91d3db3"
      billing_address: {
        same_as_shipping: true
      }
    }
  ) {
    cart {
      billing_address {
        firstname
        lastname
        street
        city
        region {
          code
        }
        postcode
        country {
          code
        }
      }
    }
  }
}
```

## Set Shipping Method (Flat Rate)
```
mutation {
  setShippingMethodsOnCart(
    input: {
      cart_id: "fa67c95ddb4942ada5df503fa91d3db3"
      shipping_methods: [
        {
          carrier_code: "flatrate"
          method_code: "flatrate"
        }
      ]
    }
  ) {
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

## Set Payment Method (Check/Money Order)
```
mutation {
  setPaymentMethodOnCart(
    input: {
      cart_id: "fa67c95ddb4942ada5df503fa91d3db3"
      payment_method: {
        code: "checkmo"
      }
    }
  ) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
```

## Place Order
```
mutation {
  placeOrder(
    input: {
      cart_id: "fa67c95ddb4942ada5df503fa91d3db3"
    }
  ) {
    orderV2 {
      items {
        id
        product_name
      }
    }
  }
}
```