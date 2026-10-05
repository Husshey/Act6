<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');

        // Every method in this controller needs a valid access token
        $this->api->require_jwt();
    }

    public function index()
    {
        $this->api->require_method('GET');

        $stmt = $this->db->raw('SELECT * FROM products ORDER BY id DESC');
        $this->api->respond($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function show($id)
    {
        $this->api->require_method('GET');

        $product = $this->find_product((int) $id);
        $this->api->respond($product);
    }

    public function store()
    {
        $this->api->require_method('POST');
        $data = $this->validated_input($this->api->body());

        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity)
             VALUES (?, ?, ?, ?)',
            [$data['product_name'], $data['description'], $data['price'], $data['quantity']]
        );

        $new = $this->db->raw('SELECT LAST_INSERT_ID() AS id')->fetch(PDO::FETCH_ASSOC);
        $product = $this->find_product((int) $new['id']);

        $this->api->respond($product, 201);
    }

    public function update($id)
    {
        $this->api->require_method('PUT');

        $this->find_product((int) $id);
        $data = $this->validated_input($this->api->body());

        $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ?
             WHERE id = ?',
            [$data['product_name'], $data['description'], $data['price'], $data['quantity'], (int) $id]
        );

        $this->api->respond($this->find_product((int) $id));
    }

    public function destroy($id)
    {
        $this->api->require_method('DELETE');

        $this->find_product((int) $id);
        $this->db->raw('DELETE FROM products WHERE id = ?', [(int) $id]);

        $this->api->respond(['message' => 'Product deleted successfully']);
    }

    private function find_product($id)
    {
        $stmt = $this->db->raw('SELECT * FROM products WHERE id = ?', [$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->api->respond_error('Product not found', 404);
        }

        return $product;
    }

    private function validated_input($input)
    {
        $name        = trim($input['product_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $price       = $input['price'] ?? null;
        $quantity    = $input['quantity'] ?? null;

        if ($name === '' || strlen($name) > 100) {
            $this->api->respond_error('Product name is required (max 100 characters)', 422);
        }
        if (!is_numeric($price) || $price < 0) {
            $this->api->respond_error('Price must be a number, zero or higher', 422);
        }
        if (filter_var($quantity, FILTER_VALIDATE_INT) === false || $quantity < 0) {
            $this->api->respond_error('Quantity must be a whole number, zero or higher', 422);
        }

        return [
            'product_name' => $name,
            'description'  => $description,
            'price'        => round((float) $price, 2),
            'quantity'     => (int) $quantity,
        ];
    }
}