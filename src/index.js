// Our modules / classes
// import MobileMenu from "./modules/MobileMenu";

// Instantiate a new object using our modules/classes
// const mobileMenu = new MobileMenu()
// const heroSlider = new HeroSlider()
// main.js
// This is your test publishable API key.
import MyCart from "./modules/cart";
import "./modules/audio";
import { initializeDirectCheckout } from "./modules/direct-checkout";

const myCart = new MyCart();

initializeDirectCheckout();
// Mobile navigation
const btnNavEl = document.querySelector(".btn-mobile-nav");
const headerEl = document.querySelector(".main-header");

btnNavEl.addEventListener("click", function () {
  headerEl.classList.toggle("nav-open");
});
