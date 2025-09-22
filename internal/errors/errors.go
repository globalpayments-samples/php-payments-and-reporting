package errors

import (
	"time"
	"github.com/google/uuid"
	"go-payments-api/internal/logger"
)

type ErrorHandler struct {
	logger    *logger.Logger
	debugMode bool
}

type APIError struct {
	ID        string                 `json:"id"`
	Code      string                 `json:"code"`
	Message   string                 `json:"message"`
	Type      string                 `json:"type"`
	Context   string                 `json:"context"`
	Details   map[string]interface{} `json:"details,omitempty"`
	Timestamp time.Time              `json:"timestamp"`
}

type ValidationError struct {
	Field   string `json:"field"`
	Message string `json:"message"`
}

func New(logger *logger.Logger, debugMode bool) *ErrorHandler {
	return &ErrorHandler{
		logger:    logger,
		debugMode: debugMode,
	}
}

func (eh *ErrorHandler) HandleError(err error, context string) APIError {
	apiError := APIError{
		ID:        uuid.New().String(),
		Code:      "INTERNAL_ERROR",
		Message:   "An internal error occurred",
		Type:      "error",
		Context:   context,
		Timestamp: time.Now(),
	}

	if eh.debugMode {
		apiError.Message = err.Error()
	}

	eh.logger.Error("API Error", map[string]interface{}{
		"error_id": apiError.ID,
		"error":    err.Error(),
		"context":  context,
	}, logger.ChannelAPI)

	return apiError
}

func (eh *ErrorHandler) HandlePaymentError(responseCode, responseMessage string, additionalData map[string]interface{}) APIError {
	apiError := APIError{
		ID:        uuid.New().String(),
		Code:      responseCode,
		Message:   responseMessage,
		Type:      "payment_error",
		Context:   "payment_processing",
		Details:   additionalData,
		Timestamp: time.Now(),
	}

	eh.logger.Error("Payment Error", map[string]interface{}{
		"error_id":        apiError.ID,
		"response_code":   responseCode,
		"response_message": responseMessage,
		"additional_data": additionalData,
	}, logger.ChannelAPI)

	return apiError
}

func (eh *ErrorHandler) ValidateRequest(request interface{}) []ValidationError {
	// Basic validation - can be expanded
	return []ValidationError{}
}