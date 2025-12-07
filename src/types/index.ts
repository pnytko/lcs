/**
 * LuloCustoms Shop - TypeScript Types
 */

// ==========================================
// PRODUCT
// ==========================================

export interface Product {
  id: number;
  name: string;
  description: string;
  price: number;
  image_url: string | null;
  stock: number;
  active: boolean;
  created_at: string;
  updated_at: string;
}

export interface ProductFormData {
  name: string;
  description: string;
  price: number;
  stock: number;
  active: boolean;
  image?: File;
}

// ==========================================
// ORDER
// ==========================================

export interface Order {
  id: number;
  order_number: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  customer_address: string;
  total_price: number;
  payment_status: 'pending' | 'paid' | 'failed' | 'cancelled';
  p24_transaction_id: string | null;
  p24_session_id: string | null;
  created_at: string;
  items: OrderItem[];
}

export interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_price: number;
  quantity: number;
}

export interface CreateOrderData {
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  customer_address: string;
  items: {
    product_id: number;
    quantity: number;
  }[];
}

export interface OrderPublic {
  order_number: string;
  total_price: number;
  payment_status: 'pending' | 'paid' | 'failed' | 'cancelled';
  created_at: string;
}

// ==========================================
// ADMIN
// ==========================================

export interface Admin {
  id: number;
  email: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface AuthResponse {
  success: boolean;
  logged_in?: boolean;
  admin?: Admin;
  message?: string;
}

// ==========================================
// CART (localStorage)
// ==========================================

export interface CartItem {
  product: Product;
  quantity: number;
}

export interface Cart {
  items: CartItem[];
  total: number;
}

// ==========================================
// PAYMENT
// ==========================================

export interface PaymentInitResponse {
  success: boolean;
  redirect_url: string;
  session_id: string;
  token: string;
}

export interface PaymentStatusResponse {
  success: boolean;
  status: 'pending' | 'paid' | 'failed' | 'cancelled';
  order_number: string;
  total_price: number;
}

// ==========================================
// API RESPONSES
// ==========================================

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
}

export interface ProductsResponse {
  success: boolean;
  products: Product[];
}

export interface ProductResponse {
  success: boolean;
  product: Product;
}

export interface OrdersResponse {
  success: boolean;
  orders: Order[];
  total: number;
  limit: number;
  offset: number;
}

export interface OrderResponse {
  success: boolean;
  order: Order;
}

// ==========================================
// FORM VALIDATION
// ==========================================

export interface CheckoutFormData {
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  customer_address: string;
}

export interface ProductFormErrors {
  name?: string;
  description?: string;
  price?: string;
  stock?: string;
  image?: string;
}

export interface CheckoutFormErrors {
  customer_name?: string;
  customer_email?: string;
  customer_phone?: string;
  customer_address?: string;
}
