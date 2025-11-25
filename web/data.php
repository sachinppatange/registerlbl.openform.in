<?php
// Include the Razorpay PHP library
require('razorpay-php/Razorpay.php');
use Razorpay\Api\Api;

// Initialize Razorpay with your key and secret
$api_key = 'rzp_live_D53J9UWwYtGimn';
$api_secret = 'w0SnqzH2SOOIc0gnUR7cYO3r';

$api = new Api($api_key, $api_secret);
// Create an order
$order = $api->order->create([
    'amount' => 100, // amount in paise (100 paise = 1 rupee)
    'currency' => 'INR',
    'receipt' => 'order_receipt_1001'
]);
// Get the order ID
$order_id = $order->id;

// Set your callback URL
$callback_url = "https://admission.atharvmedia.com/web/paymentrecipt.php";

// Include Razorpay Checkout.js library
echo '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';

// Create a payment button with Checkout.js
//echo '<button onclick="startPayment()">Pay with Razorpay</button>';

// Add a script to handle the payment
echo '<script>
    function startPayment() {
        var options = {
            key: "' . $api_key . '",
            amount: ' . $order->amount . ',
            currency: "' . $order->currency . '",
            name: "Your Company Name",
            description: "Payment for your order",
            image: "https://cdn.razorpay.com/logos/GhRQcyean79PqE_medium.png",
            order_id: "' . $order_id . '",
            theme:
            {
                "color": "#738276"
            },
            callback_url: "' . $callback_url . '"
        };
        var rzp = new Razorpay(options);
        rzp.open();
    }
</script>';
?>
<?php
include_once './config.php';
include_once './ctrlgetStudDetails.php';

//  echo $_GET['studmaxid'];
$student=getStudentRegById($_GET['studmaxid']);


?>
<!-- <script>
    // Retrieve the PHP session variable and embed it into JavaScript
    <?php if (isset($_SESSION['message'])): ?>
        var message = "<?php echo $_SESSION['message']; ?>";
        alert(message);
    <?php endif; ?>
</script> -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form</title>
    <link rel="icon" href="../assets/img/logo.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <style>
        .size {
            font-size: 12px;
        }

        .rule li {
            font-size: 13px;
            ;
        }

        .error {
            font-size: 12px;
            color: red;
        }
    </style>
</head>
<!-- GOVINDLAL KANHAIYALAL JOSHI (NIGHT) COMMERCE COLLEGE, LATUR -->

<body style="background-color:#87CEFA">
    <div class="container">
        <div class="card p-4 m-3">
            <div class="row">
                <div class="col-md-6">
                    
                    
                    

                </div>
                <div class="col-md-6 mt-4">
                    <h6 style="color:red;"><b>Registration Fee Form</b></h6>
                    
	<form action="paymentrecipt.php" method="post">
    <div class="row">
        <div class="col-md-12 mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php if (isset($student['stud_name'])) echo $student['stud_name'] ?>" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-3">
            <label for="contact" class="form-label">Contact Number</label>
            <input type="tel" class="form-control" id="contact" name="contact" value="<?php if (isset($student['mobile'])) echo $student['mobile'] ?>" required>
        </div>
        <div class="col-md-12 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php if (isset($student['email'])) echo $student['email'] ?>" required>
        </div>
    </div>
    <div class="col-md-12 mb-3">
        <label for="address" class="form-label">Address</label>
        <input type="text" class="form-control" id="address" name="address" placeholder="Enter Address" required>
    </div>
    <div class="row">
        <div class="col-md-12 mb-3">
            <label for="city" class="form-label">City</label>
            <input type="text" class="form-control" id="city" name="city" placeholder="Enter City" required>
        </div>
        <div class="col-md-12 mb-3">
            <label for="pincode" class="form-label">Pin Code</label>
            <input type="text" class="form-control" id="pincode" name="pincode" placeholder="Enter Pin Code" required>
        </div>
        <div class="col-md-12 mb-3">
            <label for="state" class="form-label">State</label>
            <input type="text" class="form-control" id="state" name="state" placeholder="Enter State" required>
        </div>
        <div class="col-md-12 mb-3">
            <label for="country" class="form-label">Country</label>
            <input type="text" class="form-control" id="country" name="country" placeholder="Enter Country" required>
        </div>
        <div class="col-md-12 mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" class="form-control" id="amount" name="amount" value="100" readonly required>
        </div>
    </div>
    <center><button type="button" class="btn btn-primary" name="submit" onclick="startPayment()">Submit</button></center>
	
</form>

                </div>
            </div>
        </div>

    </div>
    <script>
        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        function capitalizeInput(event) {
            const input = event.target;
            const cursorPosition = input.selectionStart; // Get current cursor position

            input.value = capitalizeFirstLetter(input.value);

            // Restore the cursor position
            input.setSelectionRange(cursorPosition, cursorPosition);
        }

        $(document).ready(function () {
            $('input[type="text"], textarea').on('input', capitalizeInput);
        });
    </script>


    <?php unset($_SESSION['message']); ?>
    <?php unset($_SESSION['error1']); ?>
</body>

</html>