import axios from "axios";

class MyCart {
  constructor() {
    this.detailBtn = document.querySelector(".add_to_cart_details");
    if (this.detailBtn) {
      this.detailBtn.addEventListener("click", (e) => {
        e.preventDefault(); // Prevent default link action
        this.addProductFromDetails(this.detailBtn);
      });
    }
    this.cartRemoveButtons = document.querySelectorAll(".remove_from_cart");
    this.productQty = document.querySelectorAll(".product-quantity");
    this.cartBadge = document.querySelector(".cart-badge");
    axios.defaults.headers.common["X-WP-Nonce"] = mrjData.nonce;
    this.cartItems = [];
    this.events();
    this.loadCartItems();
    if (window.location.href.includes("/shop/cart/")) {
      this.loadCartItems();
      this.updateTotalOnBackend();
      this.payButton = document.querySelector(".pay-button");
      if (this.payButton) {
        this.payButton.addEventListener("click", (event) => {
          event.preventDefault(); // Prevent form submission
          this.checkout();
        });
      }
      this.initializeCart();
    }
  }

  events() {
    const cartButtons = document.querySelectorAll(".add_to_cart");
    cartButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        this.createCartItem(button);
      });
    });

    this.cartRemoveButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        const programElement = e.target.closest(".program");
        const productId = programElement.getAttribute("data-id");
        const cartItem = this.cartItems.find(
          (item) => item.productId === productId
        );

        if (cartItem) {
          console.log(cartItem.id); // Ensure you have a valid id before calling removeItemFromCart
          this.removeItemFromCart(cartItem.title, cartItem.id);
        } else {
          button.style.disabled = true;
        }
      });
    });
  }

  async addProductFromDetails(button) {
    const productElement = button.closest(".product");
    const title = productElement
      .querySelector(".heading-primary")
      .textContent.trim();
    const price = parseFloat(button.getAttribute("data-price"));
    const productId = button.getAttribute("data-id");
    const quantity = 1; // Since it's the details page, you're likely adding one item at a time.

    const existingItemIndex = this.cartItems.findIndex(
      (item) => item.productId == productId
    );
    if (existingItemIndex > -1) {
      this.cartItems[existingItemIndex].quantity += quantity;
      this.updateCartUI(); // You should update the cart UI to reflect the new quantity
    } else {
      const cartItem = { title, price, quantity, productId };
      this.cartItems.push(cartItem);
      await this.addItemToCart(cartItem, button); // Using await here assumes that addItemToCart is an async function
      this.updateCartUI(); // Update the cart UI after adding the new item
    }
  }

  async initializeCart() {
    await this.loadCartItems();
    if (window.location.href.includes("/shop/cart/")) {
      this.updateCartUI();
    }
    this.initializeProductQuantities();
  }

  initializeProductQuantities() {
    const programElements = document.querySelectorAll(".program");

    programElements.forEach((programElement) => {
      const productId = programElement.getAttribute("data-id");

      if (productId) {
        const quantityElement =
          programElement.querySelector(".product-quantity");
        const cartItem = this.cartItems.find(
          (item) => item.productId == productId
        );
        quantityElement.textContent = cartItem ? cartItem.quantity : "0";
      } else {
        console.error(
          "data-id attribute is missing or incorrect in .program element"
        );
      }
    });
  }
  async decrementCartItem(productId, programElement) {
    const quantityElement = programElement.querySelector(".product-quantity");
    const cartItemIndex = this.cartItems.findIndex(
      (item) => item.productId === productId
    );

    if (cartItemIndex > -1) {
      const cartItem = this.cartItems[cartItemIndex];

      if (cartItem.quantity > 1) {
        cartItem.quantity--; // Decrease the quantity
        quantityElement.textContent = cartItem.quantity;
        await this.updateCartItem(cartItem); // Update cart in the backend if necessary
      } else {
        await this.removeItemFromCart(cartItem.title, cartItem.id); // Remove the item from cart if quantity is 1 or less
      }
    } else {
      console.error("Item not found in cart");
    }
  }
  updateCartBadge() {
    const itemCount = this.cartItems.length;
    if (this.cartBadge) {
      this.cartBadge.textContent = itemCount.toString();
    }
  }
  async createCartItem(button) {
    const programElement = button.closest(".program");
    const title = programElement
      .querySelector(".program-title")
      .textContent.trim();
    const price = parseFloat(
      programElement
        .querySelector(".program-price")
        .textContent.replace("Price: $", "")
    );
    const productId = button.getAttribute("data-id"); // Assuming you have a data attribute for the product's permanent ID
    const quantityElement = programElement.querySelector(".product-quantity");
    const existingItemIndex = this.cartItems.findIndex(
      (item) => item.productId == productId
    );

    if (existingItemIndex > -1) {
      quantityElement.textContent = this.cartItems[existingItemIndex].quantity; // Update UI quantity

      button.disabled = true;
    } else {
      const cartItem = { title, price, quantity: 1, productId };
      this.cartItems.push(cartItem);
      quantityElement.textContent = "1"; // Initialize UI quantity
      await this.addItemToCart(cartItem, button, programElement);
    }
  }

  async addItemToCart(item, button, programElement) {
    try {
      const payload = {
        title: item.title,
        price: item.price,
        quantity: item.quantity,
        productId: item.productId,
        status: "private",
      };

      const response = await axios.post(
        `${mrjData.root_url}/wp-json/wp/v2/cart`,
        payload,
        {
          headers: {
            "X-WP-Nonce": mrjData.nonce,
            "Content-Type": "application/json",
          },
          withCredentials: true,
        }
      );

      if (response.data && response.data.cartItemId) {
        await this.loadCartItems();
        this.updateCartBadge();
      } else {
        console.error(
          "Failed to add item to the cart: No cart item ID received from the backend."
        );
      }
    } catch (e) {
      console.error("Failed to add item to the cart:", e);
    }
  }

  async loadCartItems() {
    try {
      const response = await axios.get(
        `${mrjData.root_url}/wp-json/wp/v2/cart/`,
        {
          withCredentials: true,
        }
      );

      this.cartItems = response.data.map((item) => ({
        title: item.title.rendered || item.title,
        id: item.id,
        price: item.acf.program_price,
        quantity: item.program_quantity, // Assuming this is how the backend sends the quantity
        productId: item.product_id,
      }));

      this.updateCartUI();
      this.updateCartBadge();
      this.initializeProductQuantities();

      this.cartItems.forEach((item) => {
        const addButton = document.querySelector(
          `.add_to_cart[data-id="${item.productId}"]`
        );
        const quantityElement = document.querySelector(
          `.program[data-id="${item.productId}"] .product-quantity`
        );
        if (addButton) {
          addButton.disabled = item.quantity > 0; // Here you can manage if quantity affects button state
        }
        if (quantityElement) {
          quantityElement.textContent = item.quantity || "0";
        }
      });
    } catch (e) {
      console.error("Failed to load cart items:", e);
    }
  }

  updateCartUI() {
    const cartItemsContainer = document.getElementById("cart-items");
    const cartTotalElement = document.getElementById("cart-total");
    if (this.cartItems.length === 0) {
      // If the cart is empty, display a message and a link to continue shopping
      cartItemsContainer.innerHTML = `
        <div class="empty-cart">
        <p class="emoty-cart">Your cart is empty. <a href="/">Add items to continue the purchase.</a></p>
          </div>
        </div>
      `;
      if (cartTotalElement) {
        cartTotalElement.textContent = "Total to Pay: $0.00";
      }
    } else {
      if (cartItemsContainer && cartTotalElement) {
        cartItemsContainer.innerHTML = "";
        this.cartItems.forEach((item) => {
          const cartItemDiv = document.createElement("div");
          cartItemDiv.className = "cart-item";
          cartItemDiv.innerHTML = `
          <span class="bin-icon" data-id="${item.id}">
              <i class="fas fa-trash delete-item"></i>
          </span>
          <span class="product-name">${item.title}</span>
          <span class="product-amount">$${item.price.toFixed(2)}</span>
        `;

          cartItemsContainer.appendChild(cartItemDiv);

          const binIcon = cartItemDiv.querySelector(".bin-icon");
          binIcon.addEventListener("click", (e) => {
            e.preventDefault();
            this.removeItemFromCart(item.title);
          });
        });

        const total = this.cartItems.reduce((sum, item) => sum + item.price, 0);
        cartTotalElement.textContent = `Total to Pay: $${total.toFixed(2)}`;
      }
    }
  }

  async removeItemFromCart(itemOrTitle, itemId) {
    let itemToRemove = this.cartItems.find(
      (item) =>
        item.id === itemId ||
        this.normalizeTitle(item.title) === this.normalizeTitle(itemOrTitle)
    );

    if (!itemToRemove) {
      console.error("Item to remove is missing an ID or title");
      return;
    }

    try {
      const response = await axios.delete(
        `${mrjData.root_url}/wp-json/wp/v2/cart/${itemToRemove.id}`,
        {
          headers: { "X-WP-Nonce": mrjData.nonce },
        }
      );

      if (response.status === 200) {
        this.cartItems = this.cartItems.filter(
          (item) => item.id !== itemToRemove.id
        );

        this.initializeProductQuantities();
        this.updateCartBadge();
        this.updateCartUI(); //this updates the entire cart UI properly
      } else {
        throw new Error(`Failed to delete item with ID: ${itemToRemove.id}`);
      }
    } catch (error) {
      console.error("Failed to remove item:", error);
    }
  }
  async updateTotalOnBackend() {
    try {
      const response = await axios.get(
        `${mrjData.root_url}/wp-json/mrj/v1/cart-total`,
        {
          headers: {
            "X-WP-Nonce": mrjData.nonce,
            "Content-Type": "application/json",
          },
        }
      );

      if (response.data) {
        console.log("Total updated successfully:", response.data);
        // You can now use response.data.cartTotal, response.data.items, etc.
      } else {
        console.error("Failed to update total: No data received");
      }
    } catch (error) {
      console.error("Error updating total:", error);
    }
  }

  async checkout() {
    try {
      const cartTotalResponse = await axios.get(
        `${mrjData.root_url}/wp-json/mrj/v1/cart-total`,
        {
          headers: {
            "X-WP-Nonce": mrjData.nonce,
            "Content-Type": "application/json",
          },
        }
      );

      if (cartTotalResponse.data && cartTotalResponse.data.items.length > 0) {
        console.log("This is product", cartTotalResponse.data);
        console.log("User ID being sent:", mrjData.userId); // Log the user ID being sent
        const checkoutData = {
          items: cartTotalResponse.data.items,
          user_id: mrjData.userId, // Make sure this is being collected correctly
        };

        console.log("Sending checkout data:", checkoutData); // Log the full payload being sent to checkout

        const checkoutResponse = await axios.post(
          `${mrjData.root_url}/wp-json/mrj/v1/checkout`,
          checkoutData,
          {
            headers: {
              "X-WP-Nonce": mrjData.nonce,
              "Content-Type": "application/json",
            },
          }
        );

        if (checkoutResponse.data && checkoutResponse.data.url) {
          window.location.href = checkoutResponse.data.url;
        } else {
          console.error("Failed to initiate checkout:", checkoutResponse.data);
        }
      } else {
        console.error(
          "Failed to fetch cart total or no items in cart:",
          cartTotalResponse.data
        );
      }
    } catch (error) {
      console.error(
        "Error during checkout process:",
        error.response ? error.response.data : error.message
      );
    }
  }

  normalizeTitle(title) {
    var textArea = document.createElement("textarea");
    textArea.innerHTML = title;
    return textArea.value.trim();
  }
  calculateTotal() {
    let total = 0;
    this.cartItems.forEach((item) => {
      total += item.price * item.quantity;
    });
    return total;
  }
}

export default MyCart;
