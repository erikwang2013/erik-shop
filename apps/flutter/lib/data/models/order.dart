class Order {
  final String id;
  final String orderNo;
  final int status;
  final String? statusText;
  final double totalAmount;
  final double payAmount;
  final String currencyCode;
  final String? createdAt;
  final String? paidAt;
  final String? payMethod;
  final List<OrderItem>? items;

  Order({
    required this.id, required this.orderNo, required this.status,
    this.statusText, required this.totalAmount, required this.payAmount,
    required this.currencyCode, this.createdAt, this.paidAt, this.payMethod, this.items,
  });

  factory Order.fromJson(Map<String, dynamic> json) => Order(
    id: json['id'] ?? '',
    orderNo: json['order_no'] ?? '',
    status: json['status'] ?? 0,
    statusText: json['status_text'],
    totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0,
    payAmount: (json['pay_amount'] as num?)?.toDouble() ?? 0,
    currencyCode: json['currency_code'] ?? 'USD',
    createdAt: json['created_at'],
    paidAt: json['paid_at'],
    payMethod: json['pay_method'],
    items: (json['items'] as List?)?.map((e) => OrderItem.fromJson(e)).toList(),
  );
}

class OrderItem {
  final String id;
  final String title;
  final String? image;
  final double price;
  final int quantity;
  final double subtotal;

  OrderItem({
    required this.id, required this.title, this.image,
    required this.price, required this.quantity, required this.subtotal,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) => OrderItem(
    id: json['id'] ?? '', title: json['title'] ?? '',
    image: json['image'], price: (json['price'] as num?)?.toDouble() ?? 0,
    quantity: json['quantity'] ?? 1, subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0,
  );
}
