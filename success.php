<?php
/*
Template Name: Success Page
*/
get_header();
?>

<section>
    <p>
        We appreciate your business! If you have any questions, please email
        <a href="mailto:orders@example.com">orders@example.com</a>.
    </p>
    <p id="userDetails"></p>
</section>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');

    fetch(`/wp-json/mrj/v1/session?session_id=${sessionId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log(data);
    let userDetailsHtml = `Order placed by ${data.username} (${data.email})`;
    if (data.products && Array.isArray(data.products)) {
        userDetailsHtml += '<ul>';
        data.products.forEach(product => {
            // Assuming product.productId is directly accessible
            userDetailsHtml += `<li>${product.name} - Total: $${product.price} - Product Id: ${product.productId}</li>`;
        });
        userDetailsHtml += '</ul>';
    } else {
        userDetailsHtml += '<p>No product details available.</p>';
    }

    document.getElementById('userDetails').innerHTML = userDetailsHtml;
})

    .catch(error => {
        console.error('Fetch error:', error);
    });
});


</script>



<?php
get_footer();
?>
