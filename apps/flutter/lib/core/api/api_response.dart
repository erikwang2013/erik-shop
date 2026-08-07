class ApiResponse<T> {
  final int code;
  final String msg;
  final T? data;

  bool get isSuccess => code == 0;

  ApiResponse({required this.code, required this.msg, this.data});

  static ApiResponse<T> fromJson<T>(Map<String, dynamic> json) {
    return ApiResponse(
      code: json['code'] ?? -1,
      msg: json['msg'] ?? 'Unknown error',
      data: json['data'] as T?,
    );
  }
}

class PaginatedData<T> {
  final List<T> list;
  final int total;
  final int page;
  final int perPage;

  bool get hasMore => page * perPage < total;

  PaginatedData({
    required this.list,
    required this.total,
    required this.page,
    required this.perPage,
  });

  factory PaginatedData.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) fromJsonT) {
    return PaginatedData(
      list: (json['list'] as List).map((e) => fromJsonT(e as Map<String, dynamic>)).toList(),
      total: json['total'] ?? 0,
      page: json['page'] ?? 1,
      perPage: json['per_page'] ?? 20,
    );
  }
}
