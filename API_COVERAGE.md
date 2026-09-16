# API Coverage

All API routes found in the Flutter app are implemented in `index.php`.

| Flutter Process | Method | Route | Implemented |
|---|---:|---|---:|
| Register employee | POST | `/employee/register` | yes |
| Login | POST | `/login` | yes |
| Profile and roles | POST | `/profile/information` | yes |
| Add center | POST | `/add_center` | yes |
| List centers | POST | `/centers` | yes |
| Center details | POST | `/centers/information` | yes |
| Center roles | POST | `/centers/roles` | yes |
| All employees | POST | `/show_employees` | yes |
| Grant employee role | POST | `/grant_role` | yes |
| Center employees | POST | `/center/employees` | yes |
| Add product | POST | `/centers/products` | yes |
| List products | POST | `/product/show/all` | yes |
| List product categories | POST | `/product/categories/index` | yes |
| Update product | POST | `/product/update` | yes |
| Delete product | POST | `/product/delete` | yes |
| Delete multiple products | POST | `/products/multidelete` | yes |
| Import products / bulk category update | POST | `/products/multistore` | yes |
| Upload product images after import | POST | `/products/images/upload` | yes |
| Delete product image | POST | `/product/image/delete` | yes |
| Today sales | POST | `/TodaySales` | yes |
| Daily sales chart/list | POST | `/Center/DailySales` | yes |
| Month sales chart/list | POST | `/Center/MonthSales` | yes |
| Year sales chart/list | POST | `/Center/YearSales` | yes |
| Statistics | POST | `/Statistics` | yes |

## Payload Notes

### `/products/multistore`

Expected body:

```json
{
  "products": [
    {
      "product_number": "1",
      "name": "Product name",
      "description": "-",
      "price": "100",
      "center_id": 1,
      "code": "-",
      "unit": 1,
      "category": "Category name",
      "quantity": 10
    }
  ]
}
```

If `product_number` matches an existing product ID or existing product number for the same center, the product is updated. Otherwise it is inserted.

### `/product/show/all`

Supports pagination:

```text
POST /product/show/all?page=1
```

Body:

```json
{
  "center_id": 1,
  "category_id": 2
}
```

`category_id` is optional.
