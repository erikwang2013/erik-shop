/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { ApiClient } from '../common/api/ApiClient';
import { ProductCard, ProductCardItem } from '../common/components/ProductCard';

@Entry
@Component
struct Search {
  @State keyword: string = '';
  @State results: Array<ProductCardItem> = [];
  @State totalCount: number = 0;
  @State loading: boolean = false;
  @State searched: boolean = false;
  private api: ApiClient = ApiClient.getInstance();

  async doSearch() {
    if (!this.keyword.trim()) return;
    this.loading = true;
    this.searched = true;
    const res = await this.api.get('/search', { keyword: this.keyword, per_page: 40 });
    if (res.code === 0) {
      const data = res.data as Record<string, Object>;
      this.results = (data['list'] as Array<ProductCardItem>) ?? [];
      this.totalCount = (data['total'] as number) ?? 0;
    }
    this.loading = false;
  }

  build() {
    Column() {
      Row() {
        Image($r('app.media.arrow_back')).width(24).height(24).onClick(() => router.back())
        TextInput({ placeholder: '搜索商品...', text: this.keyword })
          .layoutWeight(1).margin({ left: 8, right: 8 })
          .onChange((value: string) => { this.keyword = value })
          .onSubmit(() => { this.doSearch() })
        Button('搜索').fontSize(14).onClick(() => this.doSearch())
      }.width('100%').padding(12)

      if (this.loading) {
        Column() { LoadingProgress().width(40).height(40) }
          .width('100%').height('60%').justifyContent(FlexAlign.Center)
      } else if (this.searched) {
        Text('共' + this.totalCount + '个结果').fontSize(12).fontColor('#999').width('100%').padding(16)

        if (this.results.length === 0) {
          Column() {
            Image($r('app.media.empty_search')).width(80).height(80).opacity(0.3)
            Text('未找到相关商品').fontSize(14).fontColor('#999').margin({ top: 8 })
          }.width('100%').height('50%').justifyContent(FlexAlign.Center)
        } else {
          Grid() {
            ForEach(this.results, (item: ProductCardItem) => {
              GridItem() { ProductCard({ product: item }) }
            })
          }.columnsTemplate('1fr 1fr').rowsGap(8).columnsGap(8).padding(12)
        }
      }
    }.width('100%').height('100%')
  }
}
