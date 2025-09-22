package handlers

import (
	"net/http"
	"strings"
	"github.com/gin-gonic/gin"
	"go-payments-api/internal/config"
	"go-payments-api/internal/logger"
	"go-payments-api/internal/errors"
)

type TransactionHandler struct {
	config       *config.Config
	logger       *logger.Logger
	errorHandler *errors.ErrorHandler
}

type TransactionListRequest struct {
	StartDate     string `form:"start_date"`
	EndDate       string `form:"end_date"`
	TransactionID string `form:"transaction_id"`
	Status        string `form:"status"`
	Limit         int    `form:"limit" validate:"min=1,max=100"`
	Page          int    `form:"page" validate:"min=1"`
}

type Transaction struct {
	ID            string                 `json:"id"`
	Reference     string                 `json:"reference"`
	Status        string                 `json:"status"`
	Amount        string                 `json:"amount"`
	Currency      string                 `json:"currency"`
	Type          string                 `json:"type"`
	Timestamp     string                 `json:"timestamp"`
	Card          map[string]interface{} `json:"card"`
	Response      map[string]interface{} `json:"response"`
}

type TransactionListResponse struct {
	Success      bool          `json:"success"`
	Transactions []Transaction `json:"transactions"`
	Pagination   struct {
		Page       int `json:"page"`
		Limit      int `json:"limit"`
		Total      int `json:"total"`
		TotalPages int `json:"total_pages"`
	} `json:"pagination"`
}

func NewTransactionHandler(cfg *config.Config, log *logger.Logger, eh *errors.ErrorHandler) *TransactionHandler {
	return &TransactionHandler{
		config:       cfg,
		logger:       log,
		errorHandler: eh,
	}
}

func (h *TransactionHandler) GetTransactions(c *gin.Context) {
	h.logger.Info("Transaction list request received", nil, logger.ChannelAPI)

	// Parse request parameters for filtering
	var req TransactionListRequest
	if err := c.ShouldBindQuery(&req); err != nil {
		apiErr := h.errorHandler.HandleError(err, "Invalid request parameters")
		c.JSON(http.StatusBadRequest, apiErr)
		return
	}

	// Set defaults
	if req.Limit == 0 {
		req.Limit = 25
	}
	if req.Page == 0 {
		req.Page = 1
	}

	// Get real transactions from the transaction store
	allTransactions := GetTransactionStore().GetTransactions()

	// Filter transactions based on request parameters
	filteredTransactions := []Transaction{}
	
	for _, txn := range allTransactions {
		// Filter by status
		if req.Status != "" && txn.Status != req.Status {
			continue
		}
		
		// Filter by transaction ID (partial match)
		if req.TransactionID != "" {
			if !contains(txn.ID, req.TransactionID) && !contains(txn.Reference, req.TransactionID) {
				continue
			}
		}
		
		// TODO: Add date filtering logic when req.StartDate and req.EndDate are provided
		// For now, include all transactions
		
		filteredTransactions = append(filteredTransactions, txn)
	}

	// Calculate pagination
	total := len(filteredTransactions)
	totalPages := (total + req.Limit - 1) / req.Limit
	startIndex := (req.Page - 1) * req.Limit
	endIndex := startIndex + req.Limit
	
	if startIndex >= total {
		filteredTransactions = []Transaction{}
	} else {
		if endIndex > total {
			endIndex = total
		}
		filteredTransactions = filteredTransactions[startIndex:endIndex]
	}

	response := TransactionListResponse{
		Success:      true,
		Transactions: filteredTransactions,
	}
	response.Pagination.Page = req.Page
	response.Pagination.Limit = req.Limit
	response.Pagination.Total = total
	response.Pagination.TotalPages = totalPages

	c.JSON(http.StatusOK, response)
}

// Helper function to check if a string contains a substring (case-insensitive)
func contains(str, substr string) bool {
	return len(str) >= len(substr) && 
		   (str == substr || 
		    len(substr) == 0 ||
		    containsIgnoreCase(str, substr))
}

func containsIgnoreCase(str, substr string) bool {
	// Simple case-insensitive contains check
	strLower := strings.ToLower(str)
	substrLower := strings.ToLower(substr)
	
	for i := 0; i <= len(strLower)-len(substrLower); i++ {
		if strLower[i:i+len(substrLower)] == substrLower {
			return true
		}
	}
	return false
}