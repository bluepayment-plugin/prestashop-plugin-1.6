jQuery(document).ready(function ($) {
    var placeOrder = $("#bm-place-order");
    placeOrder.attr("bm-place-order-text", placeOrder.html());
});
function selectBluePayment() {
    document.getElementById("blue_payway").style.display = "block";
    return false;
}
function showBlueButtonNext() {
    document.getElementById("cart_navigation").style.display = "block";
    return false;
}
function selectBluePaymentGateway(url, gatewayID) {
    document.getElementById("cart_navigation").style.display = "block";
    document.getElementById('blue_payment_next').href = url;

    var button = jQuery("#bm-place-order");
    var regulation = jQuery('#bm-enclosure');

    if (gatewayID >= 1800) {
        var regulationLabel = jQuery('#bm-regulation-input-label-' + gatewayID);
        regulation.html(regulationLabel.html());
        regulation.show();
        button.html('Rozpocznij płatność<i class="icon-chevron-right right"></i>');
    } else {
        regulation.hide();
        button.html(button.attr("bm-place-order-text"));
    }

    return false;
}
function makeBluePaymentGateway() {
    document.getElementById('blue_payment_next').style.pointerEvents = "none";
    document.getElementById('blue_payment_next').style.cursor = "default";
    window.location.href = document.getElementById('blue_payment_next').href;

    return false;
}