//language=JSON
module.exports = (searchText, searchTextLemmatized) => `
  {
    "from": 0,
    "size": 11,
    "post_filter": {
      "bool": {
        "must": [
          {
            "terms": {
              "post_type.raw": [
                "disease",
                "substance",
                "custom_quiz"
              ]
            }
          },
          {
            "terms": {
              "post_status": [
                "publish"
              ]
            }
          },
          {
            "bool": {
              "must_not": [
                {
                  "terms": {
                    "meta.ep_exclude_from_search.raw": [
                      "1"
                    ]
                  }
                }
              ]
            }
          }
        ]
      }
    },
    "query": {
      "bool": {
        "should": [
          {
            "bool": {
              "must": [
                {
                  "bool": {
                    "should": [
                      {
                        "nested": {
                          "path": "post_titles",
                          "score_mode": "max",
                          "query": {
                            "function_score": {
                              "query": {
                                "bool": {
                                  "should": [
                                    {
                                      "match_phrase": {
                                        "post_titles.title_lemma.title_lemma": {
                                          "query": "${searchTextLemmatized}",
                                          "boost": 4
                                        }
                                      }
                                    },
                                    {
                                      "match": {
                                        "post_titles.title_lemma.title_lemma": {
                                          "query": "${searchTextLemmatized}",
                                          "analyzer": "c_text_stop",
                                          "operator": "and",
                                          "boost": 1.5
                                        }
                                      }
                                    },
                                    {
                                      "match": {
                                        "post_titles.title_lemma.suggest": {
                                          "query": "${searchText}",
                                          "analyzer": "c_text_stop",
                                          "operator": "and"
                                        }
                                      }
                                    }
                                  ]
                                }
                              },
                              "boost": 6,
                              "functions": [
                                {
                                  "weight": 10
                                },
                                {
                                  "field_value_factor": {
                                    "field": "post_titles.title_lemma.len",
                                    "modifier": "reciprocal",
                                    "factor": 0.5,
                                    "missing": 1
                                  }
                                }
                              ],
                              "boost_mode": "multiply",
                              "score_mode": "multiply"
                            }
                          },
                          "inner_hits": {
                            "name": "disease_titles_hits",
                            "size": 5,
                            "highlight": {
                              "fields": {
                                "post_titles.title_lemma": {
                                  "fragment_size": 150,
                                  "number_of_fragments": 10
                                }
                              }
                            }
                          }
                        }
                      },
                      {
                        "match": {
                          "post_titles_flat": {
                            "query": "${searchTextLemmatized}",
                            "analyzer": "c_text_stop",
                            "operator": "and",
                            "boost": 3
                          }
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "phrase",
                          "slop": 2,
                          "fields": [
                            "terms.symptoms.name^1",
                            "terms.specialty.name^1",
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1",
                            "term_suggest^1"
                          ],
                          "boost": 3
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "phrase_prefix",
                          "slop": 2,
                          "fields": [
                            "terms.symptoms.name^1",
                            "terms.specialty.name^1",
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1",
                            "term_suggest^1"
                          ],
                          "operator": "and",
                          "boost": 2
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "fields": [
                            "terms.symptoms.name^1",
                            "terms.specialty.name^1",
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1"
                          ],
                          "analyzer": "c_text_stop",
                          "operator": "and",
                          "boost": 1,
                          "fuzziness": 0
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "cross_fields",
                          "fields": [
                            "terms.symptoms.name^1",
                            "terms.specialty.name^1",
                            "post_titles_flat_lemma^1",
                            "post_content_lemma^1"
                          ],
                          "boost": 1,
                          "analyzer": "c_text_stop",
                          "tie_breaker": 0.5,
                          "operator": "and"
                        }
                      }
                    ]
                  }
                },
                {
                  "terms": {
                    "terms.article-type.slug": [
                      "article",
                      "clinical-guidelines"
                    ]
                  }
                }
              ],
              "filter": [
                {
                  "match": {
                    "post_type.raw": "disease"
                  }
                }
              ]
            }
          },
          {
            "bool": {
              "must": [
                {
                  "bool": {
                    "should": [
                      {
                        "nested": {
                          "path": "post_titles",
                          "score_mode": "max",
                          "query": {
                            "function_score": {
                              "query": {
                                "bool": {
                                  "should": [
                                    {
                                      "match_phrase": {
                                        "post_titles.title_lemma.title_lemma": {
                                          "query": "${searchTextLemmatized}",
                                          "boost": 4
                                        }
                                      }
                                    },
                                    {
                                      "match": {
                                        "post_titles.title_lemma.title_lemma": {
                                          "query": "${searchTextLemmatized}",
                                          "analyzer": "c_text_stop",
                                          "operator": "and",
                                          "boost": 1.5
                                        }
                                      }
                                    },
                                    {
                                      "match": {
                                        "post_titles.title_lemma.suggest": {
                                          "query": "${searchText}",
                                          "analyzer": "c_text_stop",
                                          "operator": "and"
                                        }
                                      }
                                    }
                                  ]
                                }
                              },
                              "boost": 6,
                              "functions": [
                                {
                                  "weight": 10
                                },
                                {
                                  "field_value_factor": {
                                    "field": "post_titles.title_lemma.len",
                                    "modifier": "reciprocal",
                                    "factor": 0.5,
                                    "missing": 1
                                  }
                                }
                              ],
                              "boost_mode": "multiply",
                              "score_mode": "multiply"
                            }
                          },
                          "inner_hits": {
                            "name": "substance_titles_hits",
                            "size": 5,
                            "highlight": {
                              "fields": {
                                "post_titles.title_lemma": {
                                  "fragment_size": 150,
                                  "number_of_fragments": 10
                                }
                              }
                            }
                          }
                        }
                      },
                      {
                        "match": {
                          "post_titles_flat": {
                            "query": "${searchTextLemmatized}",
                            "analyzer": "c_text_stop",
                            "operator": "and",
                            "boost": 3
                          }
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "phrase",
                          "slop": 2,
                          "fields": [
                            "terms.drug-classes.name^1",
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1",
                            "term_suggest^1"
                          ],
                          "boost": 3
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "phrase_prefix",
                          "slop": 2,
                          "fields": [
                            "terms.drug-classes.name^1",
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1",
                            "term_suggest^1"
                          ],
                          "operator": "and",
                          "boost": 2
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "fields": [
                            "terms.drug-classes.name^1",
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1"
                          ],
                          "analyzer": "c_text_stop",
                          "operator": "and",
                          "boost": 1,
                          "fuzziness": 0
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "cross_fields",
                          "fields": [
                            "terms.drug-classes.name^1",
                            "post_titles_flat_lemma^1",
                            "post_content_lemma^1"
                          ],
                          "boost": 1,
                          "analyzer": "c_text_stop",
                          "tie_breaker": 0.5,
                          "operator": "and"
                        }
                      }
                    ]
                  }
                }
              ],
              "filter": [
                {
                  "match": {
                    "post_type.raw": "substance"
                  }
                }
              ]
            }
          },
          {
            "bool": {
              "must": [
                {
                  "bool": {
                    "should": [
                      {
                        "nested": {
                          "path": "post_titles",
                          "score_mode": "max",
                          "query": {
                            "function_score": {
                              "query": {
                                "bool": {
                                  "should": [
                                    {
                                      "match_phrase": {
                                        "post_titles.title_lemma.title_lemma": {
                                          "query": "${searchTextLemmatized}",
                                          "boost": 4
                                        }
                                      }
                                    },
                                    {
                                      "match": {
                                        "post_titles.title_lemma.title_lemma": {
                                          "query": "${searchTextLemmatized}",
                                          "analyzer": "c_text_stop",
                                          "operator": "and",
                                          "boost": 1.5
                                        }
                                      }
                                    },
                                    {
                                      "match": {
                                        "post_titles.title_lemma.suggest": {
                                          "query": "${searchText}",
                                          "analyzer": "c_text_stop",
                                          "operator": "and"
                                        }
                                      }
                                    }
                                  ]
                                }
                              },
                              "boost": 6,
                              "functions": [
                                {
                                  "weight": 10
                                },
                                {
                                  "field_value_factor": {
                                    "field": "post_titles.title_lemma.len",
                                    "modifier": "reciprocal",
                                    "factor": 0.5,
                                    "missing": 1
                                  }
                                }
                              ],
                              "boost_mode": "multiply",
                              "score_mode": "multiply"
                            }
                          },
                          "inner_hits": {
                            "name": "custom_quiz_titles_hits",
                            "size": 5,
                            "highlight": {
                              "fields": {
                                "post_titles.title_lemma": {
                                  "fragment_size": 150,
                                  "number_of_fragments": 10
                                }
                              }
                            }
                          }
                        }
                      },
                      {
                        "match": {
                          "post_titles_flat": {
                            "query": "${searchTextLemmatized}",
                            "analyzer": "c_text_stop",
                            "operator": "and",
                            "boost": 3
                          }
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "phrase",
                          "slop": 2,
                          "fields": [
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1",
                            "term_suggest^1"
                          ],
                          "boost": 3
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "phrase_prefix",
                          "slop": 2,
                          "fields": [
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1",
                            "term_suggest^1"
                          ],
                          "operator": "and",
                          "boost": 2
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "fields": [
                            "post_titles_flat_lemma^20",
                            "post_content_lemma^1"
                          ],
                          "analyzer": "c_text_stop",
                          "operator": "and",
                          "boost": 1,
                          "fuzziness": 0
                        }
                      },
                      {
                        "multi_match": {
                          "query": "${searchTextLemmatized}",
                          "type": "cross_fields",
                          "fields": [
                            "post_titles_flat_lemma^1",
                            "post_content_lemma^1"
                          ],
                          "boost": 1,
                          "analyzer": "c_text_stop",
                          "tie_breaker": 0.5,
                          "operator": "and"
                        }
                      }
                    ]
                  }
                }
              ],
              "filter": [
                {
                  "match": {
                    "post_type.raw": "custom_quiz"
                  }
                }
              ]
            }
          }
        ]
      }
    },
    "sort": [
      {
        "_score": {
          "order": "desc"
        }
      }
    ],
    "highlight": {
      "order": "score",
      "fields": {
        "post_content": {
          "type": "fvh",
          "number_of_fragments": 5,
          "fragment_size": 60,
          "boundary_scanner": "word",
          "boundary_scanner_locale": "ru-RU",
          "pre_tags": [
            "<mark class='ep-highlight'>"
          ],
          "post_tags": [
            "<\/mark>"
          ],
          "highlight_query": {
            "bool": {
              "should": [
                {
                  "match_phrase": {
                    "post_content": {
                      "query": "${searchText}",
                      "boost": 3,
                      "slop": 2
                    }
                  }
                },
                {
                  "match": {
                    "post_content": {
                      "query": "${searchText}",
                      "analyzer": "c_text_stem_stop",
                      "operator": "and"
                    }
                  }
                }
              ]
            }
          }
        }
      }
    }
  }`