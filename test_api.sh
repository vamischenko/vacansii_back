#!/bin/bash

API_URL="${1:-http://localhost:8080}"

echo "================================"
echo "Тестирование API вакансий"
echo "URL: $API_URL"
echo "================================"
echo ""

echo "1. Тест: Получение списка вакансий"
echo "GET $API_URL/vacancy"
echo ""
curl -s -X GET "$API_URL/vacancy" | python3 -m json.tool
echo ""
echo "---"
echo ""

echo "2. Тест: Получение списка вакансий с сортировкой по зарплате (убывание)"
echo "GET $API_URL/vacancy?sort=salary&order=desc"
echo ""
curl -s -X GET "$API_URL/vacancy?sort=salary&order=desc" | python3 -m json.tool
echo ""
echo "---"
echo ""

echo "3. Тест: Получение списка вакансий (страница 2)"
echo "GET $API_URL/vacancy?page=2"
echo ""
curl -s -X GET "$API_URL/vacancy?page=2" | python3 -m json.tool
echo ""
echo "---"
echo ""

echo "4. Тест: Создание новой вакансии"
echo "POST $API_URL/vacancy"
echo ""
RESPONSE=$(curl -s -X POST "$API_URL/vacancy" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Vacancy",
    "description": "Тестовая вакансия для проверки API",
    "salary": 100000,
    "additional_fields": {
      "company": "Test Company",
      "location": "Test Location"
    }
  }')

echo "$RESPONSE" | python3 -m json.tool
VACANCY_ID=$(echo "$RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('id', 1))" 2>/dev/null)
echo ""
echo "---"
echo ""

echo "5. Тест: Получение конкретной вакансии (ID: $VACANCY_ID)"
echo "GET $API_URL/vacancy/$VACANCY_ID"
echo ""
curl -s -X GET "$API_URL/vacancy/$VACANCY_ID" | python3 -m json.tool
echo ""
echo "---"
echo ""

echo "6. Тест: Получение вакансии с выбранными полями"
echo "GET $API_URL/vacancy/$VACANCY_ID?fields=title,salary"
echo ""
curl -s -X GET "$API_URL/vacancy/$VACANCY_ID?fields=title,salary" | python3 -m json.tool
echo ""
echo "---"
echo ""

echo "7. Тест: Создание вакансии с ошибкой валидации (отсутствует обязательное поле)"
echo "POST $API_URL/vacancy"
echo ""
curl -s -X POST "$API_URL/vacancy" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Invalid Vacancy"
  }' | python3 -m json.tool
echo ""
echo "---"
echo ""

echo "================================"
echo "Тестирование завершено"
echo "================================"
