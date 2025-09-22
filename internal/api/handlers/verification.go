package handlers

import (
	"bytes"
	"crypto/sha512"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"time"
	"github.com/gin-gonic/gin"
	"go-payments-api/internal/config"
	"go-payments-api/internal/logger"
	"go-payments-api/internal/errors"
)

type VerificationHandler struct {
	config       *config.Config
	logger       *logger.Logger
	errorHandler *errors.ErrorHandler
}

type VerificationRequest struct {
	PaymentToken string `json:"payment_token" validate:"required"`
	CVV          string `json:"cvv,omitempty"`
	Address      struct {
		Street     string `json:"street,omitempty"`
		City       string `json:"city,omitempty"`
		PostalCode string `json:"postal_code,omitempty"`
		Country    string `json:"country,omitempty"`
	} `json:"address,omitempty"`
}

type VerificationResponse struct {
	Success        bool   `json:"success"`
	TransactionID  string `json:"transaction_id,omitempty"`
	Status         string `json:"status,omitempty"`
	AVSResponse    string `json:"avs_response,omitempty"`
	CVVResponse    string `json:"cvv_response,omitempty"`
	Message        string `json:"message,omitempty"`
	ProcessingTime int    `json:"processing_time_ms,omitempty"`
}

// GP-API Verification Request structure
type GPApiVerificationRequest struct {
	AccountName   string                 `json:"account_name"`
	Channel       string                 `json:"channel"`
	Currency      string                 `json:"currency"`
	Country       string                 `json:"country"`
	Reference     string                 `json:"reference,omitempty"`
	PaymentMethod map[string]interface{} `json:"payment_method"`
}

// GP-API Verification Response structure
type GPApiVerificationResponse struct {
	ID              string                 `json:"id"`
	TimeCreated     string                 `json:"time_created"`
	Status          string                 `json:"status"`
	Channel         string                 `json:"channel"`
	Amount          int                    `json:"amount"`
	Currency        string                 `json:"currency"`
	Country         string                 `json:"country"`
	Reference       string                 `json:"reference,omitempty"`
	Description     string                 `json:"description"`
	PaymentMethod   map[string]interface{} `json:"payment_method"`
	ActionType      string                 `json:"action_type"`
	Action          map[string]interface{} `json:"action,omitempty"`
	GatewayResponse map[string]interface{} `json:"gateway_response,omitempty"`
}

func NewVerificationHandler(cfg *config.Config, log *logger.Logger, eh *errors.ErrorHandler) *VerificationHandler {
	return &VerificationHandler{
		config:       cfg,
		logger:       log,
		errorHandler: eh,
	}
}

func (h *VerificationHandler) VerifyCard(c *gin.Context) {
	h.logger.Info("Card verification request received", nil, logger.ChannelVerification)

	var request VerificationRequest
	if err := c.ShouldBindJSON(&request); err != nil {
		h.logger.Error("Invalid verification request", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelVerification)
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"message": "Invalid request format: " + err.Error(),
		})
		return
	}

	h.logger.Info("Processing card verification", map[string]interface{}{
		"has_token": request.PaymentToken != "",
		"has_cvv": request.CVV != "",
	}, logger.ChannelVerification)

	if request.PaymentToken == "" {
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"message": "Payment token is required",
		})
		return
	}

	// Generate access token for verification
	accessToken, err := h.generateVerificationAccessToken()
	if err != nil {
		h.logger.Error("Failed to generate access token for verification", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelVerification)
		c.JSON(http.StatusInternalServerError, VerificationResponse{
			Success: false,
			Message: "Authentication failed: " + err.Error(),
		})
		return
	}

	// Process the verification with GP-API
	verificationResult, err := h.processGPApiVerification(accessToken, request)
	if err != nil {
		h.logger.Error("GP-API verification failed", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelVerification)

		// Store failed verification for dashboard tracking
		failedVerification := Transaction{
			ID:        fmt.Sprintf("VERIFY_FAILED_%d", time.Now().Unix()),
			Reference: fmt.Sprintf("VER_%d", time.Now().Unix()),
			Status:    "failed",
			Amount:    "0.00",
			Currency:  "USD",
			Type:      "verification",
			Timestamp: time.Now().UTC().Format(time.RFC3339),
			Card: map[string]interface{}{
				"brand":               "UNKNOWN",
				"masked_number_last4": "****",
				"expiry_month":        "**",
				"expiry_year":         "**",
			},
			Response: map[string]interface{}{
				"response_code":    "96",
				"response_message": "VERIFICATION_ERROR",
				"error":           err.Error(),
			},
		}
		GetTransactionStore().AddTransaction(failedVerification)

		c.JSON(http.StatusInternalServerError, VerificationResponse{
			Success: false,
			Message: "Verification failed: " + err.Error(),
		})
		return
	}

	// Store successful verification for dashboard tracking
	verification := Transaction{
		ID:        verificationResult.ID,
		Reference: verificationResult.Reference,
		Status:    verificationResult.Status,
		Amount:    "0.00",
		Currency:  verificationResult.Currency,
		Type:      "verification",
		Timestamp: verificationResult.TimeCreated,
		Card:      extractCardInfo(verificationResult.PaymentMethod),
		Response:  buildVerificationResponseInfo(verificationResult),
	}
	GetTransactionStore().AddTransaction(verification)

	// Return success response in format expected by frontend
	response := map[string]interface{}{
		"success": true,
		"verification_result": map[string]interface{}{
			"id":           verificationResult.ID,
			"status":       verificationResult.Status,
			"currency":     verificationResult.Currency,
			"time_created": verificationResult.TimeCreated,
			"payment_method": verificationResult.PaymentMethod,
			"action":       verificationResult.Action,
		},
		"message": "Card verification successful",
	}

	h.logger.Info("Card verification completed successfully", map[string]interface{}{
		"transaction_id": verificationResult.ID,
		"status":         verificationResult.Status,
	}, logger.ChannelVerification)

	c.JSON(http.StatusOK, response)
}

// generateVerificationAccessToken generates an access token for verification using the same logic as payments
func (h *VerificationHandler) generateVerificationAccessToken() (string, error) {
	apiURL := "https://apis.sandbox.globalpay.com/ucp/accesstoken"
	if h.config.GPApiEnvironment == "production" {
		apiURL = "https://apis.globalpay.com/ucp/accesstoken"
	}

	// Generate nonce
	nonce := strconv.FormatInt(time.Now().UnixNano(), 10) + generateRandomString(20)

	// Generate secret
	secret := h.generateSecret(nonce)

	requestPayload := map[string]interface{}{
		"app_id":     h.config.GPApiAppID,
		"nonce":      nonce,
		"secret":     secret,
		"grant_type": "client_credentials",
		// No permissions specified = full merchant account access (same as payment handler)
	}

	jsonData, err := json.Marshal(requestPayload)
	if err != nil {
		return "", fmt.Errorf("failed to marshal token request: %w", err)
	}

	req, err := http.NewRequest("POST", apiURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return "", fmt.Errorf("failed to create token request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("token request failed: %w", err)
	}
	defer resp.Body.Close()

	var tokenResponse struct {
		Token string `json:"token"`
		Error string `json:"error"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&tokenResponse); err != nil {
		return "", fmt.Errorf("failed to decode token response: %w", err)
	}

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("token request failed with status %d: %s", resp.StatusCode, tokenResponse.Error)
	}

	if tokenResponse.Token == "" {
		return "", fmt.Errorf("empty token received")
	}

	return tokenResponse.Token, nil
}

// processGPApiVerification processes a verification using $0.00 authorization (no actual charge)
func (h *VerificationHandler) processGPApiVerification(accessToken string, request VerificationRequest) (*GPApiVerificationResponse, error) {
	apiURL := "https://apis.sandbox.globalpay.com/ucp/transactions"
	if h.config.GPApiEnvironment == "production" {
		apiURL = "https://apis.globalpay.com/ucp/transactions"
	}

	// Generate unique reference for this verification
	reference := fmt.Sprintf("VER_%d", time.Now().Unix())

	// Create a $0.00 authorization request for verification (no actual charge)
	verificationRequest := map[string]interface{}{
		"account_name": "transaction_processing",
		"channel":      "CNP",
		"type":         "SALE", // Use SALE but with $0.00 amount
		"amount":       0,      // $0.00 - no charge, just verification
		"currency":     "USD",
		"country":      "US",
		"reference":    reference,
		"description":  "Card verification - no charge",
		"payment_method": map[string]interface{}{
			"id":         request.PaymentToken,
			"entry_mode": "ECOM",
		},
	}

	jsonData, err := json.Marshal(verificationRequest)
	if err != nil {
		return nil, fmt.Errorf("failed to marshal verification request: %w", err)
	}

	h.logger.Info("GP-API verification request", map[string]interface{}{
		"url":     apiURL,
		"payload": string(jsonData),
	}, logger.ChannelVerification)

	req, err := http.NewRequest("POST", apiURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return nil, fmt.Errorf("failed to create verification request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Authorization", "Bearer "+accessToken)
	req.Header.Set("X-GP-Version", "2021-03-22")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("verification request failed: %w", err)
	}
	defer resp.Body.Close()

	h.logger.Info("GP-API verification response", map[string]interface{}{
		"status_code": resp.StatusCode,
	}, logger.ChannelVerification)

	// Read response body for logging
	var responseBody []byte
	responseBody, err = io.ReadAll(resp.Body)
	if err == nil {
		h.logger.Info("GP-API verification response body", map[string]interface{}{
			"body": string(responseBody),
		}, logger.ChannelVerification)
	}

	// Parse response
	var verificationResponse GPApiVerificationResponse
	if err := json.Unmarshal(responseBody, &verificationResponse); err != nil {
		return nil, fmt.Errorf("failed to decode verification response: %w", err)
	}

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, fmt.Errorf("verification failed with status %d", resp.StatusCode)
	}

	return &verificationResponse, nil
}

// generateSecret generates the secret hash for GP-API authentication
func (h *VerificationHandler) generateSecret(nonce string) string {
	// Concatenate app_id + nonce + secret
	secretString := h.config.GPApiAppID + nonce + h.config.GPApiAppKey
	
	// Generate SHA512 hash
	secretHash := sha512.Sum512([]byte(secretString))
	
	// Return hex encoded hash
	return hex.EncodeToString(secretHash[:])
}

// generateRandomString generates a random string for nonce
func generateRandomString(length int) string {
	const charset = "abcdefghijklmnopqrstuvwxyz0123456789"
	result := make([]byte, length)
	for i := range result {
		result[i] = charset[time.Now().UnixNano()%int64(len(charset))]
	}
	return string(result)
}

// buildVerificationResponseInfo builds response information from verification result
func buildVerificationResponseInfo(verificationResult *GPApiVerificationResponse) map[string]interface{} {
	responseCode := "XX"
	responseMessage := "UNKNOWN"

	switch verificationResult.Status {
	case "VERIFIED":
		responseCode = "00"
		responseMessage = "VERIFIED"
	case "NOT_VERIFIED":
		responseCode = "05"
		responseMessage = "NOT_VERIFIED"
	case "FAILED":
		responseCode = "96"
		responseMessage = "VERIFICATION_FAILED"
	default:
		responseMessage = verificationResult.Status
	}

	responseInfo := map[string]interface{}{
		"response_code":    responseCode,
		"response_message": responseMessage,
		"transaction_id":   verificationResult.ID,
		"status":          verificationResult.Status,
		"channel":         verificationResult.Channel,
	}

	// Add action information if available
	if verificationResult.Action != nil {
		responseInfo["action"] = verificationResult.Action
		
		// Extract specific verification results
		if avsResult, ok := verificationResult.Action["avs_response_code"].(string); ok {
			responseInfo["avs_result"] = avsResult
		}
		if cvvResult, ok := verificationResult.Action["cvv_response_code"].(string); ok {
			responseInfo["cvv_result"] = cvvResult
		}
	}

	return responseInfo
}

// Helper function to generate test IDs (kept for backward compatibility)
func generateTestID() string {
	return fmt.Sprintf("%d", time.Now().UnixMilli()%1000000)
}