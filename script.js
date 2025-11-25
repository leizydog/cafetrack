const products = {
  coffee: [
    { name: "Iced Latte", price: 90, img: "https://images.unsplash.com/photo-1572441710534-680e1b70d9e5?w=400" },
    { name: "Espresso", price: 150, img: "https://images.unsplash.com/photo-1572448858771-5c6b9f95d29b?w=400" },
    { name: "Cappuccino", price: 120, img: "https://images.unsplash.com/photo-1511920170033-f8396924c348?w=400" },
    { name: "Spanish Latte", price: 125, img: "https://images.unsplash.com/photo-1617196039915-c76e064e05d3?w=400" },
    { name: "Americano", price: 110, img: "https://images.unsplash.com/photo-1512568400610-62da28bc8a13?w=400" },
    { name: "Caramel Macchiato", price: 135, img: "https://images.unsplash.com/photo-1617196039864-cc678ec80f68?w=400" }
  ],
  milktea: [
    { name: "Classic MilkTea", price: 80, img: "https://images.unsplash.com/photo-1582542057353-a6c91d0a9d05?w=400" },
    { name: "Wintermelon", price: 95, img: "https://images.unsplash.com/photo-1617196039948-6c14aa5cc9f8?w=400" },
  ],
  fruittea: [
    { name: "Mango Fruit Tea", price: 85, img: "https://images.unsplash.com/photo-1604908177522-0406937a9a2f?w=400" },
    { name: "Strawberry Fruit Tea", price: 90, img: "https://images.unsplash.com/photo-1622547748225-f2a12f1a60f1?w=400" },
  ]
};

let cart = JSON.parse(localStorage.getItem("cart")) || [];

function showCategory(category) {
  document.querySelectorAll(".tabs button").forEach(btn => btn.classList.remove("active"));
  event.target.classList.add("active");

  const list = document.getElementById("product-list");
  list.innerHTML = "";
  products[category].forEach(p => {
    list.innerHTML += `
      <div class="product">
        <img src="${p.img}" alt="${p.name}">
        <h3>${p.name}</h3>
        <p>₱${p.price}</p>
        <button onclick="addToCart('${p.name}', ${p.price})">Add to cart</button>
      </div>
    `;
  });
}

function addToCart(name, price) {
  let item = cart.find(i => i.name === name);
  if (item) {
    item.qty++;
  } else {
    cart.push({ name, price, qty: 1 });
  }
  localStorage.setItem("cart", JSON.stringify(cart));
  updateCartCount();
}

function updateCartCount() {
  const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
  document.getElementById("cart-count").textContent = totalItems;
}

function goToCheckout() {
  window.location.href = "checkout.php";
}

// show default
showCategory('coffee');
updateCartCount();
