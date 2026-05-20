class Product {
  final String id;
  final String title;
  final String? subtitle;
  final String? mainImage;
  final String? description;
  final String? brand;
  final double minPrice;
  final double maxPrice;
  final int status;
  final bool isHot;
  final bool isNew;
  final int salesCount;
  final List<ProductSku>? skus;
  final List<ProductImage>? images;
  final Map<String, dynamic>? displayPrice;

  Product({
    required this.id, required this.title, this.subtitle, this.mainImage,
    this.description, this.brand, required this.minPrice, required this.maxPrice,
    required this.status, this.isHot = false, this.isNew = false,
    this.salesCount = 0, this.skus, this.images, this.displayPrice,
  });

  factory Product.fromJson(Map<String, dynamic> json) => Product(
    id: json['id'] ?? '',
    title: json['title'] ?? '',
    subtitle: json['subtitle'],
    mainImage: json['main_image'],
    description: json['description'],
    brand: json['brand'],
    minPrice: (json['min_price'] as num?)?.toDouble() ?? 0,
    maxPrice: (json['max_price'] as num?)?.toDouble() ?? 0,
    status: json['status'] ?? 0,
    isHot: json['is_hot'] ?? false,
    isNew: json['is_new'] ?? false,
    salesCount: json['sales_count'] ?? 0,
    skus: (json['skus'] as List?)?.map((e) => ProductSku.fromJson(e)).toList(),
    images: (json['images'] as List?)?.map((e) => ProductImage.fromJson(e)).toList(),
    displayPrice: json['display_price'],
  );
}

class ProductSku {
  final String id;
  final String productId;
  final String? skuCode;
  final Map<String, dynamic>? attrs;
  final double defaultPrice;
  final int stock;
  final String? image;

  ProductSku({
    required this.id, required this.productId, this.skuCode, this.attrs,
    required this.defaultPrice, required this.stock, this.image,
  });

  factory ProductSku.fromJson(Map<String, dynamic> json) => ProductSku(
    id: json['id'] ?? '',
    productId: json['product_id'] ?? '',
    skuCode: json['sku_code'],
    attrs: json['attrs'],
    defaultPrice: (json['default_price'] as num?)?.toDouble() ?? 0,
    stock: json['stock'] ?? 0,
    image: json['image'],
  );
}

class ProductImage {
  final String id;
  final String url;
  final bool isMain;

  ProductImage({required this.id, required this.url, this.isMain = false});
  factory ProductImage.fromJson(Map<String, dynamic> json) => ProductImage(
    id: json['id'] ?? '', url: json['url'] ?? '', isMain: json['is_main'] ?? false,
  );
}
