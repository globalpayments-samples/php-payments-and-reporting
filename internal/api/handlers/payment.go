package handlers

import (
	"bytes"
	"crypto/sha512"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"
	"github.com/gin-gonic/gin"
	"go-payments-api/internal/config"
	"go-payments-api/internal/logger"
	"go-payments-api/internal/errors"
)

type PaymentHandler struct {
	config       *config.Config
	logger       *logger.Logger
	errorHandler *errors.ErrorHandler
}

type PaymentRequest struct {
	PaymentToken string                 `json:"payment_token" validate:"required"`
	Amount       float64                `json:"amount" validate:"required,min=0.01,max=999999.99"`
	Currency     string                 `json:"currency" validate:"required,len=3"`
	Description  string                 `json:"description,omitempty"`
	OrderID      string                 `json:"order_id,omitempty"`
	CardDetails  map[string]interface{} `json:"card_details,omitempty"`
}

type PaymentResponse struct {
	Success       bool                   `json:"success"`
	TransactionID string                 `json:"transaction_id,omitempty"`
	Status        string                 `json:"status,omitempty"`
	Amount        float64                `json:"amount,omitempty"`
	Currency      string                 `json:"currency,omitempty"`
	Message       string                 `json:"message,omitempty"`
	PaymentResult map[string]interface{} `json:"payment_result,omitempty"`
	Error         *errors.APIError       `json:"error,omitempty"`
}

// GP-API Transaction Request structure
type GPApiTransactionRequest struct {
	AccountName   string                 `json:"account_name"`
	Channel       string                 `json:"channel"`
	Type          string                 `json:"type,omitempty"`
	Amount        int                    `json:"amount"`
	Currency      string                 `json:"currency"`
	Country       string                 `json:"country"`
	Reference     string                 `json:"reference,omitempty"`
	Description   string                 `json:"description,omitempty"`
	PaymentMethod map[string]interface{} `json:"payment_method"`
}

// GP-API Transaction Response structure
type GPApiTransactionResponse struct {
	ID            string                 `json:"id"`
	TimeCreated   string                 `json:"time_created"`
	Status        string                 `json:"status"`
	Channel       string                 `json:"channel"`
	Amount        string                 `json:"amount"` // GP-API returns amount as string
	Currency      string                 `json:"currency"`
	Reference     string                 `json:"reference,omitempty"`
	Description   string                 `json:"description,omitempty"`
	OrderReference string                `json:"order_reference,omitempty"`
	PaymentMethod map[string]interface{} `json:"payment_method"`
	Action        map[string]interface{} `json:"action,omitempty"`
	AuthorizationCode string             `json:"authorization_code,omitempty"`
	BatchID       string                 `json:"batch_id,omitempty"`
}

func NewPaymentHandler(cfg *config.Config, log *logger.Logger, eh *errors.ErrorHandler) *PaymentHandler {
	return &PaymentHandler{
		config:       cfg,
		logger:       log,
		errorHandler: eh,
	}
}

func (h *PaymentHandler) ProcessPayment(c *gin.Context) {
	h.logger.Info("Payment processing request received", nil, logger.ChannelAPI)

	var request PaymentRequest
	if err := c.ShouldBindJSON(&request); err != nil {
		h.logger.Error("Invalid payment request", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelAPI)
		c.JSON(http.StatusBadRequest, PaymentResponse{
			Success: false,
			Message: "Invalid request format: " + err.Error(),
		})
		return
	}

	// Log the payment request details
	h.logger.Info("Processing payment", map[string]interface{}{
		"amount":   request.Amount,
		"currency": request.Currency,
		"token":    request.PaymentToken[:10] + "...",
	}, logger.ChannelAPI)

	// Generate access token for transaction processing
	accessToken, err := h.generateTransactionAccessToken()
	if err != nil {
		h.logger.Error("Failed to generate access token for transaction", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelAPI)
		c.JSON(http.StatusInternalServerError, PaymentResponse{
			Success: false,
			Message: "Failed to generate access token: " + err.Error(),
		})
		return
	}

	// Process the payment with GP-API
	transactionResult, err := h.processGPApiTransaction(accessToken, request)
	if err != nil {
		h.logger.Error("GP-API transaction failed", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelAPI)
		
		// Store failed transaction for dashboard tracking
		failedTransaction := Transaction{
			ID:        fmt.Sprintf("FAILED_%d", time.Now().Unix()),
			Reference: fmt.Sprintf("PMT_%d", time.Now().Unix()),
			Status:    "failed",
			Amount:    fmt.Sprintf("%.2f", request.Amount),
			Currency:  request.Currency,
			Type:      "payment",
			Timestamp: time.Now().UTC().Format(time.RFC3339),
			Card: map[string]interface{}{
				"brand":               "UNKNOWN",
				"masked_number_last4": "****",
				"expiry_month":        "**",
				"expiry_year":         "**",
			},
			Response: map[string]interface{}{
				"response_code":    "96",
				"response_message": "SYSTEM_ERROR",
				"error":           err.Error(),
			},
		}
		GetTransactionStore().AddTransaction(failedTransaction)
		
		c.JSON(http.StatusInternalServerError, PaymentResponse{
			Success: false,
			Message: "Transaction failed: " + err.Error(),
		})
		return
	}

	// Return success response with transaction details
	response := PaymentResponse{
		Success:       true,
		TransactionID: transactionResult.ID,
		Status:        transactionResult.Status,
		Amount:        request.Amount,
		Currency:      request.Currency,
		Message:       "Payment processed successfully",
		PaymentResult: map[string]interface{}{
			"id":                 transactionResult.ID,
			"time_created":       transactionResult.TimeCreated,
			"status":             transactionResult.Status,
			"amount":             transactionResult.Amount,
			"currency":           transactionResult.Currency,
			"reference":          transactionResult.Reference,
			"description":        transactionResult.Description,
			"payment_method":     transactionResult.PaymentMethod,
			"action":             transactionResult.Action,
			"authorization_code": transactionResult.AuthorizationCode,
			"batch_id":           transactionResult.BatchID,
		},
	}

	// Store the transaction for dashboard display
	transaction := Transaction{
		ID:        transactionResult.ID,
		Reference: transactionResult.Reference,
		Status:    transactionResult.Status,
		Amount:    transactionResult.Amount,
		Currency:  transactionResult.Currency,
		Type:      "payment",
		Timestamp: transactionResult.TimeCreated,
		Card:      extractCardInfo(transactionResult.PaymentMethod),
		Response:  buildResponseInfo(transactionResult),
	}
	
	// Add to global transaction store
	GetTransactionStore().AddTransaction(transaction)

	h.logger.Info("Payment processed successfully", map[string]interface{}{
		"transaction_id": transactionResult.ID,
		"status":         transactionResult.Status,
		"amount":         transactionResult.Amount,
	}, logger.ChannelAPI)

	c.JSON(http.StatusOK, response)
}

func (h *PaymentHandler) generateTransactionAccessToken() (string, error) {
	// Use the same access token generation logic as in access_token.go
	// We need transaction permissions (not just tokenization)
	
	if h.config.GPApiAppID == "" || h.config.GPApiAppKey == "" {
		return "", fmt.Errorf("GP-API credentials not configured")
	}

	// Determine the GP-API endpoint based on environment
	var apiURL string
	if h.config.GPApiEnvironment == "production" {
		apiURL = "https://apis.globalpay.com/ucp/accesstoken"
	} else {
		apiURL = "https://apis.sandbox.globalpay.com/ucp/accesstoken"
	}

	// Generate nonce and secret
	nonce := generateTimestampNonce()
	secret := h.generateSecret(nonce)

	// Generate access token for transaction processing (no specific permissions = full access)
	tokenRequest := map[string]interface{}{
		"app_id":     h.config.GPApiAppID,
		"nonce":      nonce,
		"secret":     secret,
		"grant_type": "client_credentials",
		// No permissions specified = full merchant account access for transactions
	}

	jsonData, err := json.Marshal(tokenRequest)
	if err != nil {
		return "", fmt.Errorf("failed to marshal token request: %w", err)
	}

	h.logger.Info("Generating transaction access token", map[string]interface{}{
		"url":   apiURL,
		"nonce": nonce,
	}, logger.ChannelAPI)

	req, err := http.NewRequest("POST", apiURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return "", fmt.Errorf("failed to create token request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-GP-Version", "2021-03-22")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("token request failed: %w", err)
	}
	defer resp.Body.Close()

	// Read response body for logging
	var responseBody bytes.Buffer
	responseBody.ReadFrom(resp.Body)
	responseString := responseBody.String()

	h.logger.Info("Transaction access token response", map[string]interface{}{
		"status_code": resp.StatusCode,
		"body":        responseString,
	}, logger.ChannelAPI)

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("token request failed with status %d: %s", resp.StatusCode, responseString)
	}

	var tokenResponse map[string]interface{}
	if err := json.Unmarshal(responseBody.Bytes(), &tokenResponse); err != nil {
		return "", fmt.Errorf("failed to decode token response: %w", err)
	}

	token, ok := tokenResponse["token"].(string)
	if !ok {
		return "", fmt.Errorf("invalid token response format")
	}

	h.logger.Info("Transaction access token generated successfully", map[string]interface{}{
		"token": token[:10] + "...",
	}, logger.ChannelAPI)

	return token, nil
}

func (h *PaymentHandler) processGPApiTransaction(accessToken string, request PaymentRequest) (*GPApiTransactionResponse, error) {
	// Determine the GP-API transactions endpoint
	var apiURL string
	if h.config.GPApiEnvironment == "production" {
		apiURL = "https://apis.globalpay.com/ucp/transactions"
	} else {
		apiURL = "https://apis.sandbox.globalpay.com/ucp/transactions"
	}

	// Convert amount to cents (GP-API expects integer amounts in smallest currency unit)
	amountCents := int(request.Amount * 100)

	// Generate unique reference for this transaction
	reference := fmt.Sprintf("PMT_%d", time.Now().Unix())

	// Create transaction request
	transactionRequest := GPApiTransactionRequest{
		AccountName: "transaction_processing", // Account name for transaction processing
		Channel:     "CNP",                    // Card Not Present
		Type:        "SALE",                   // Transaction type
		Amount:      amountCents,
		Currency:    request.Currency,
		Reference:   reference,
		Description: request.Description,
		Country:     "US", // Required country field
		PaymentMethod: map[string]interface{}{
			"id":         request.PaymentToken, // Use the tokenized card from frontend
			"entry_mode": "ECOM",               // E-commerce entry mode for CNP transactions
		},
	}

	// Add order reference if provided
	if request.OrderID != "" {
		transactionRequest.Reference = request.OrderID
	}

	jsonData, err := json.Marshal(transactionRequest)
	if err != nil {
		return nil, fmt.Errorf("failed to marshal transaction request: %w", err)
	}

	h.logger.Info("GP-API transaction request", map[string]interface{}{
		"url":     apiURL,
		"payload": string(jsonData),
	}, logger.ChannelAPI)

	req, err := http.NewRequest("POST", apiURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return nil, fmt.Errorf("failed to create transaction request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+accessToken)
	req.Header.Set("X-GP-Version", "2021-03-22")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("transaction request failed: %w", err)
	}
	defer resp.Body.Close()

	// Read the response body
	var responseBody bytes.Buffer
	responseBody.ReadFrom(resp.Body)
	responseString := responseBody.String()

	h.logger.Info("GP-API transaction response", map[string]interface{}{
		"status_code": resp.StatusCode,
		"body":        responseString,
	}, logger.ChannelAPI)

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, fmt.Errorf("transaction failed with status %d: %s", resp.StatusCode, responseString)
	}

	var transactionResponse GPApiTransactionResponse
	if err := json.Unmarshal(responseBody.Bytes(), &transactionResponse); err != nil {
		return nil, fmt.Errorf("failed to decode transaction response: %w", err)
	}

	return &transactionResponse, nil
}

// Helper functions
func generateTimestampNonce() string {
	return strconv.FormatInt(time.Now().UnixNano(), 10)
}

func (h *PaymentHandler) generateSecret(nonce string) string {
	// Generate SHA512 hash of nonce + app_key (same as access_token.go)
	secretHash := sha512.Sum512([]byte(nonce + h.config.GPApiAppKey))
	return hex.EncodeToString(secretHash[:])
}

// extractCardInfo extracts card information from the payment method response
func extractCardInfo(paymentMethod map[string]interface{}) map[string]interface{} {
	cardInfo := map[string]interface{}{
		"brand":               "UNKNOWN",
		"masked_number_last4": "****",
		"expiry_month":        "**",
		"expiry_year":         "**",
	}

	if paymentMethod != nil {
		// Extract card brand if available
		if brand, ok := paymentMethod["brand"].(string); ok {
			cardInfo["brand"] = brand
		}
		
		// Extract masked card number if available
		if maskedNumber, ok := paymentMethod["masked_number_last4"].(string); ok {
			cardInfo["masked_number_last4"] = maskedNumber
		}
		
		// Extract expiry information if available
		if expiryMonth, ok := paymentMethod["expiry_month"].(string); ok {
			cardInfo["expiry_month"] = expiryMonth
		}
		
		if expiryYear, ok := paymentMethod["expiry_year"].(string); ok {
			cardInfo["expiry_year"] = expiryYear
		}
		
		// Try to extract from nested card object if it exists
		if card, ok := paymentMethod["card"].(map[string]interface{}); ok {
			if brand, ok := card["brand"].(string); ok {
				cardInfo["brand"] = brand
			}
			if maskedNumber, ok := card["masked_number_last4"].(string); ok {
				cardInfo["masked_number_last4"] = maskedNumber
			}
			if expiryMonth, ok := card["expiry_month"].(string); ok {
				cardInfo["expiry_month"] = expiryMonth
			}
			if expiryYear, ok := card["expiry_year"].(string); ok {
				cardInfo["expiry_year"] = expiryYear
			}
		}
	}

	return cardInfo
}

// buildResponseInfo builds response information from the transaction result
func buildResponseInfo(transactionResult *GPApiTransactionResponse) map[string]interface{} {
	// Map GP-API status to response codes
	responseCode := "XX" // Default unknown
	responseMessage := "UNKNOWN"

	switch transactionResult.Status {
	case "PREAUTHORIZED", "CAPTURED":
		responseCode = "00"
		responseMessage = "APPROVED"
	case "DECLINED":
		responseCode = "05"
		responseMessage = "DECLINED"
	case "REJECTED":
		responseCode = "12"
		responseMessage = "REJECTED"
	case "TIMEOUT":
		responseCode = "91"
		responseMessage = "TIMEOUT"
	case "FAILED":
		responseCode = "96"
		responseMessage = "FAILED"
	default:
		responseMessage = transactionResult.Status
	}

	responseInfo := map[string]interface{}{
		"response_code":    responseCode,
		"response_message": responseMessage,
		"transaction_id":   transactionResult.ID,
		"status":          transactionResult.Status,
		"channel":         transactionResult.Channel,
	}

	// Add authorization code if available
	if transactionResult.AuthorizationCode != "" {
		responseInfo["auth_code"] = transactionResult.AuthorizationCode
	}

	// Add batch ID if available
	if transactionResult.BatchID != "" {
		responseInfo["batch_id"] = transactionResult.BatchID
	}

	// Add action information if available
	if transactionResult.Action != nil {
		responseInfo["action"] = transactionResult.Action
	}

	return responseInfo
}