package main

import (
	"encoding/json"
	"fmt"
	"log"
	"os"

	"github.com/zeromicro/go-zero/tools/goctl/api/parser"
)

func main() {
	if len(os.Args) < 2 {
		fmt.Fprintf(os.Stderr, "用法: %s <api-file-path>\n", os.Args[0])
		os.Exit(1)
	}

	apiFile := os.Args[1]

	// 解析 API 文件
	apiSpec, err := parser.Parse(apiFile)
	if err != nil {
		log.Fatalf("解析 API 文件失败: %v", err)
	}

	jsonBytes, err := json.Marshal(apiSpec)
	if err != nil {
		log.Fatalf("生成 JSON 失败: %v", err)
	}
	fmt.Println(string(jsonBytes))
}
