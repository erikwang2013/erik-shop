import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../features/home/home_screen.dart';
import '../features/product/product_detail_screen.dart';
import '../features/cart/cart_screen.dart';
import '../features/order/order_list_screen.dart';
import '../features/order/checkout_screen.dart';
import '../features/profile/profile_screen.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/register_screen.dart';
import '../features/product/product_list_screen.dart';
import '../features/profile/address_list_screen.dart';
import '../features/order/order_detail_screen.dart';
import '../features/order/payment_success_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();

final appRouter = GoRouter(
  navigatorKey: _rootNavigatorKey,
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (c, s) => const HomeScreen()),
    GoRoute(path: '/products', builder: (c, s) => const ProductListScreen()),
    GoRoute(path: '/product/:id', builder: (c, s) => ProductDetailScreen(productId: s.pathParameters['id']!)),
    GoRoute(path: '/cart', builder: (c, s) => const CartScreen()),
    GoRoute(path: '/checkout', builder: (c, s) => const CheckoutScreen()),
    GoRoute(path: '/orders', builder: (c, s) => const OrderListScreen()),
    GoRoute(path: '/profile', builder: (c, s) => const ProfileScreen()),
    GoRoute(path: '/addresses', builder: (c, s) => const AddressListScreen()),
    GoRoute(path: '/login', builder: (c, s) => const LoginScreen()),
    GoRoute(path: '/register', builder: (c, s) => const RegisterScreen()),
    GoRoute(path: '/order/:id', builder: (c, s) => OrderDetailScreen(orderId: s.pathParameters['id']!)),
    GoRoute(
      path: '/payment-success',
      builder: (c, s) => PaymentSuccessScreen(
        orderNo: s.uri.queryParameters['order_no'] ?? '',
        amount: s.uri.queryParameters['amount'] ?? '0.00',
        currency: s.uri.queryParameters['currency'] ?? 'USD',
      ),
    ),
  ],
);
