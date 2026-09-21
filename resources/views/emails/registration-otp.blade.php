<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Career Next - Email Verification</title>
</head>

<body style="
    margin:0;
    padding:0;
    background:#f4f6f8;
    font-family:Arial, Helvetica, sans-serif;
">

    <div style="
    max-width:600px;
    margin:40px auto;
    background:#ffffff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
">

        <div style="
        padding:25px;
        text-align:center;
        background:#f8fafc;
        border-bottom:1px solid #e5e7eb;
    ">

            <h1 style="
            margin:0;
            font-size:25px;
            color:#1f2937;
        ">
                Career Next
            </h1>

            <p style="
            margin:8px 0 0;
            color:#6b7280;
            font-size:14px;
        ">
                Email Verification
            </p>

        </div>


        <div style="padding:35px 30px;">

            <h2 style="
            margin:0 0 15px;
            color:#1f2937;
            font-size:21px;
        ">
                Hello {{ $name }},
            </h2>

            <p style="
            color:#4b5563;
            line-height:1.6;
            font-size:15px;
        ">
                Thank you for registering with Career Next.
                Please use the verification code below to verify
                your email address.
            </p>


            <div style="
            margin:30px 0;
            padding:20px;
            text-align:center;
            background:#f8fafc;
            border-radius:10px;
        ">

                <p style="
                margin:0 0 10px;
                color:#6b7280;
                font-size:13px;
            ">
                    Your verification code
                </p>

                <div style="
                font-size:34px;
                font-weight:700;
                letter-spacing:8px;
                color:#111827;
            ">
                    {{ $otp }}
                </div>

            </div>


            <p style="
            color:#6b7280;
            font-size:13px;
            line-height:1.6;
        ">
                This verification code is valid for 10 minutes.
                Please do not share this code with anyone.
            </p>

            <p style="
            color:#6b7280;
            font-size:13px;
            line-height:1.6;
        ">
                If you did not request this registration,
                you can safely ignore this email.
            </p>

        </div>


        <div style="
        padding:20px 30px;
        text-align:center;
        background:#f8fafc;
        border-top:1px solid #e5e7eb;
    ">

            <p style="
            margin:0;
            color:#9ca3af;
            font-size:12px;
        ">
                © {{ date('Y') }} Career Next. All rights reserved.
            </p>

        </div>

    </div>

</body>

</html>