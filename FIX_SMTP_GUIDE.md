# Fixing SMTP Timeout (Error 10060) in XAMPP

The error `10060` happens because your computer cannot reach Gmail's mail server. This is almost always a **Network or Firewall block**.

### Step 1: Check your `php.ini`
In XAMPP, you must have the `openssl` extension enabled to send encrypted emails.
1.  Open your **XAMPP Control Panel**.
2.  Click the **Config** button next to Apache and select **PHP (php.ini)**.
3.  Search for `extension=openssl`.
4.  If there is a semicolon (`;`) at the start, remove it so it looks like this:
    ```ini
    extension=openssl
    ```
5.  **Save the file** and **Restart Apache** in XAMPP.

### Step 2: Test with a Mobile Hotspot
Many Home Wi-Fi providers block mail ports (465/587) to prevent spam.
1.  Connect your laptop to your **Phone's Hotspot** instead of your Home Wi-Fi.
2.  Try submitting the form again. If it works, your Wi-Fi provider is blocking the mail.

### Step 3: Check Firewall / Antivirus
Your computer's security software might be blocking PHP from connecting to "unusual" ports like 465.
1.  Temporarily **Disable your Antivirus** and **Windows Firewall**.
2.  Try submitting the form. If it works, you need to add an exclusion for `php.exe`.

### Step 4: Live Server (The Real Fix)
If you cannot fix it locally, **don't worry!** Once you upload your PHP code to a live hosting account (like Bluehost, Hostinger, or your own server), it will work immediately because those servers are configured to allow mail ports.

---
**Tip:** You can check if the port is open by running this command in your PowerShell:
`Test-NetConnection smtp.gmail.com -Port 465`
If it says `TcpTestSucceeded : False`, your network is definitely blocking it.
