package main

import (
	"encoding/json"
	"fmt"
	"log"
	"os"

	"github.com/zeromicro/go-zero/tools/goctl/api/parser"
	"github.com/zeromicro/go-zero/tools/goctl/api/spec"
)

// ApiParseResult 表示解析结果的结构
type ApiParseResult struct {
	Syntax   string       `json:"syntax"`
	Info     ApiInfo      `json:"info"`
	Imports  []ApiImport  `json:"imports"`
	Types    []ApiType    `json:"types"`
	Services []ApiService `json:"services"`
}

// ApiInfo 表示 info 块信息
type ApiInfo struct {
	Title      string            `json:"title"`
	Desc       string            `json:"desc"`
	Author     string            `json:"author"`
	Date       string            `json:"date"`
	Version    string            `json:"version"`
	Email      string            `json:"email"`
	Properties map[string]string `json:"properties"`
}

// ApiImport 表示导入信息
type ApiImport struct {
	Value     string    `json:"value"`
	AsPackage string    `json:"as_package,omitempty"`
	Types     []ApiType `json:"types,omitempty"`
}

// ApiType 表示类型定义
type ApiType struct {
	Name     string            `json:"name"`
	Package  string            `json:"package,omitempty"`
	TypeName string            `json:"type_name,omitempty"`
	RawName  string            `json:"raw_name,omitempty"`
	Fields   []ApiTypeField    `json:"fields"`
	Docs     []string          `json:"docs,omitempty"`
	Enums    map[string]string `json:"enums,omitempty"`
}

// ApiTypeField 表示类型字段
type ApiTypeField struct {
	Name     string   `json:"name"`
	Type     string   `json:"type"`
	Tag      string   `json:"tag"`
	Comment  string   `json:"comment"`
	Docs     []string `json:"docs,omitempty"`
	IsInline bool     `json:"is_inline"`
	Optional bool     `json:"optional"`
}

// ApiService 表示服务定义
type ApiService struct {
	Name   string     `json:"name"`
	Server ApiServer  `json:"server"`
	Routes []ApiRoute `json:"routes"`
}

// ApiServer 表示 @server 注解信息
type ApiServer struct {
	Group      string   `json:"group"`
	Prefix     string   `json:"prefix"`
	Auth       string   `json:"auth"`
	Middleware []string `json:"middleware"`
	Timeout    string   `json:"timeout"`
}

// ApiRoute 表示路由定义
type ApiRoute struct {
	Handler            string            `json:"handler"`
	Method             string            `json:"method"`
	Path               string            `json:"path"`
	RequestType        string            `json:"request_type"`
	ResponseType       string            `json:"response_type"`
	Doc                map[string]string `json:"doc"`
	Docs               []string          `json:"docs,omitempty"`
	AtServerAnnotation map[string]string `json:"at_server_annotation,omitempty"`
}

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

	// 转换为我们的结构
	result := convertToApiParseResult(apiSpec)

	// 输出 JSON
	jsonBytes, err := json.MarshalIndent(result, "", "  ")
	if err != nil {
		log.Fatalf("生成 JSON 失败: %v", err)
	}

	fmt.Println(string(jsonBytes))
}

func convertToApiParseResult(apiSpec *spec.ApiSpec) *ApiParseResult {
	// 完全使用 go-zero 提供的数据，确保百分百兼容
	syntax := apiSpec.Syntax.Version

	result := &ApiParseResult{
		Syntax:   syntax,
		Imports:  make([]ApiImport, 0),
		Types:    make([]ApiType, 0),
		Services: make([]ApiService, 0),
	}

	// 转换 Info
	result.Info = ApiInfo{
		Title:      getInfoValue(apiSpec.Info.Properties, "title"),
		Desc:       getInfoValue(apiSpec.Info.Properties, "desc"),
		Author:     getInfoValue(apiSpec.Info.Properties, "author"),
		Date:       getInfoValue(apiSpec.Info.Properties, "date"),
		Version:    getInfoValue(apiSpec.Info.Properties, "version"),
		Email:      getInfoValue(apiSpec.Info.Properties, "email"),
		Properties: apiSpec.Info.Properties,
	}

	// 兼容处理旧的字段（如果 Properties 为空但有旧字段）
	if len(apiSpec.Info.Properties) == 0 {
		result.Info.Title = apiSpec.Info.Title
		result.Info.Desc = apiSpec.Info.Desc
		result.Info.Author = apiSpec.Info.Author
		result.Info.Version = apiSpec.Info.Version
		result.Info.Email = apiSpec.Info.Email
	}

	// 转换 Imports
	for _, imp := range apiSpec.Imports {
		apiImport := ApiImport{
			Value: imp.Value,
			Types: make([]ApiType, 0),
		}
		result.Imports = append(result.Imports, apiImport)
	}

	// 转换 Types
	for _, typ := range apiSpec.Types {
		if defineStruct, ok := typ.(*spec.DefineStruct); ok {
			apiType := convertDefineStructToApiType(defineStruct)
			result.Types = append(result.Types, apiType)
		}
	}

	// 转换 Services
	apiService := ApiService{
		Name:   apiSpec.Service.Name,
		Routes: make([]ApiRoute, 0),
	}

	// 转换所有路由组
	for _, group := range apiSpec.Service.Groups {
		// 转换 @server 注解
		if group.Annotation.Properties != nil {
			// 处理 auth 字段，可能是 "auth"、"jwt" 或 "false"
			authValue := getServerValue(group.Annotation.Properties, "auth")
			if authValue == "" {
				authValue = getServerValue(group.Annotation.Properties, "jwt")
			}

			apiService.Server = ApiServer{
				Group:      getServerValue(group.Annotation.Properties, "group"),
				Prefix:     getServerValue(group.Annotation.Properties, "prefix"),
				Auth:       authValue,
				Timeout:    getServerValue(group.Annotation.Properties, "timeout"),
				Middleware: getServerValues(group.Annotation.Properties, "middleware"),
			}
		}

		// 转换路由
		for _, route := range group.Routes {
			apiRoute := ApiRoute{
				Handler:            route.Handler,
				Method:             route.Method,
				Path:               route.Path,
				Doc:                make(map[string]string),
				Docs:               make([]string, 0),
				AtServerAnnotation: make(map[string]string),
			}

			if route.RequestType != nil {
				apiRoute.RequestType = route.RequestTypeName()
			}

			if route.ResponseType != nil {
				apiRoute.ResponseType = route.ResponseTypeName()
			}

			// 转换 @doc 注解
			if route.AtDoc.Properties != nil {
				for key, value := range route.AtDoc.Properties {
					apiRoute.Doc[key] = value
				}
			}
			if route.AtDoc.Text != "" {
				apiRoute.Doc["summary"] = route.AtDoc.Text
			}

			// 转换 Docs 数组
			if len(route.Docs) > 0 {
				apiRoute.Docs = make([]string, len(route.Docs))
				copy(apiRoute.Docs, route.Docs)
			}

			// 转换 @server 注解
			if route.AtServerAnnotation.Properties != nil {
				apiRoute.AtServerAnnotation = route.AtServerAnnotation.Properties
			}

			apiService.Routes = append(apiService.Routes, apiRoute)
		}
	}

	result.Services = append(result.Services, apiService)

	return result
}

// convertDefineStructToApiType 转换 DefineStruct 为 ApiType
func convertDefineStructToApiType(defineStruct *spec.DefineStruct) ApiType {
	apiType := ApiType{
		Name:    defineStruct.Name(),
		RawName: defineStruct.RawName,
		Fields:  make([]ApiTypeField, 0),
		Docs:    make([]string, 0),
	}

	// 转换文档
	if len(defineStruct.Docs) > 0 {
		apiType.Docs = make([]string, len(defineStruct.Docs))
		copy(apiType.Docs, defineStruct.Docs)
	}

	// 转换字段
	for _, member := range defineStruct.Members {
		apiField := ApiTypeField{
			Name:     member.Name,
			Type:     member.Type.Name(),
			Optional: member.IsOptional(),
			IsInline: member.IsInline,
			Docs:     make([]string, 0),
		}

		if member.Tag != "" {
			apiField.Tag = member.Tag
		}

		if member.Comment != "" {
			apiField.Comment = member.Comment
		}

		// 转换字段文档
		if len(member.Docs) > 0 {
			apiField.Docs = make([]string, len(member.Docs))
			copy(apiField.Docs, member.Docs)
		}

		apiType.Fields = append(apiType.Fields, apiField)
	}

	return apiType
}

func getInfoValue(properties map[string]string, key string) string {
	if value, ok := properties[key]; ok {
		return value
	}
	return ""
}

func getServerValue(properties map[string]string, key string) string {
	if value, ok := properties[key]; ok {
		return value
	}
	return ""
}

func getServerValues(properties map[string]string, key string) []string {
	if value, ok := properties[key]; ok {
		// 简单处理，实际可能需要更复杂的解析
		return []string{value}
	}
	return []string{}
}
