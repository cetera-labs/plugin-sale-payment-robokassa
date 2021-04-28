<?php
if (class_exists("\Sale\Payment")) {
    \Sale\Payment::addGateway('\SalePaymentRobokassa\Gateway');
}