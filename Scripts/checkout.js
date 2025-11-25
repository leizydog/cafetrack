let cart = JSON.parse(localStorage.getItem("cart")) || [];

function renderCart() {
  const cartItems = document.getElementById("cart-items");
  const totalEl = document.getElementById("total");
  cartItems.innerHTML = "";

  let total = 0;
  cart.forEach((item, index) => {
    total += item.price * item.qty;
    cartItems.innerHTML += `
      <tr>
        <td>${item.name}</td>
        <td>
          <button onclick="decreaseQty(${index})">-</button>
          ${item.qty}
          <button onclick="increaseQty(${index})">+</button>
        </td>
        <td>₱${item.price * item.qty}</td>
        <td><button onclick="removeItem(${index})">❌</button></td>
      </tr>
    `;
  });

  totalEl.textContent = total;
  localStorage.setItem("cart", JSON.stringify(cart));
}

function increaseQty(index) {
  cart[index].qty++;
  renderCart();
}

function decreaseQty(index) {
  if (cart[index].qty > 1) {
    cart[index].qty--;
  } else {
    cart.splice(index, 1);
  }
  renderCart();
}

function removeItem(index) {
  cart.splice(index, 1);
  renderCart();
}

function cancelOrder() {
  if (confirm("Are you sure you want to cancel the order?")) {
    cart = [];
    localStorage.removeItem("cart");
    renderCart();
  }
}

// Run on page load
renderCart();
