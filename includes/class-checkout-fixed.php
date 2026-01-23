<?php

class Unico_Checkout {
    // Properties for database connection and other necessary data
    private $db;
    private $errors = [];

    public function __construct($db) {
        $this->db = $db;
    }

    // Method to validate checkout data
    public function validateCheckoutData($data) {
        if (empty($data['name'])) {
            $this->errors[] = 'Name is required.';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Valid email is required.';
        }
        // Add more validation as per requirements

        return empty($this->errors);
    }

    // Method to handle file uploads
    public function handleFileUpload($file) {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (in_array($file['type'], $allowedMimeTypes) && $file['size'] < 2000000) { // 2MB limit
            $uploadDir = 'uploads/';
            $uploadFile = $uploadDir . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                return $uploadFile;
            } else {
                $this->errors[] = 'File upload failed.';
            }
        } else {
            $this->errors[] = 'Invalid file type or size.';
        }
        return false;
    }

    // Method to store receipt in database
    public function storeReceipt($data) {
        if ($this->validateCheckoutData($data)) {
            // Assume $this->db is a valid PDO instance
            $stmt = $this->db->prepare("INSERT INTO receipts (name, email, amount) VALUES (:name, :email, :amount)");
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':amount', $data['amount']);
            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            } else {
                $this->errors[] = 'Database error occurred.';
            }
        }
        return false;
    }

    // Method to retrieve errors
    public function getErrors() {
        return $this->errors;
    }
}

?>